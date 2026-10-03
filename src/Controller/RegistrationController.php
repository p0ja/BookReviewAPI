<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterUser;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication')]
final class RegistrationController extends AbstractController
{
    private const EMAIL_TAKEN_MSG = 'An account with this email already exists';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        // The "register" limiter from config/packages/rate_limiter.yaml.
        private readonly RateLimiterFactoryInterface $registerLimiter,
    ) {
    }

    #[OA\Post(summary: 'Create an account', description: 'The account gets ROLE_USER and can log in with /login_check straight away.', security: [])]
    #[OA\Response(
        response: 201,
        description: 'The new account',
        content: new OA\JsonContent(ref: '#/components/schemas/Account'),
    )]
    #[OA\Response(response: 409, description: 'The email is already registered', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid email, or a password shorter than 8 characters', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(
        response: 429,
        description: 'More than 10 attempts from this address in the last hour',
        headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until the next attempt is allowed', schema: new OA\Schema(type: 'integer'))],
        content: new OA\JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, #[MapRequestPayload] RegisterUser $registration): Response
    {
        // Every attempt counts, taken emails included, so the endpoint can neither be used
        // for bulk sign-ups nor to probe which emails are registered.
        $limit = $this->registerLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }

        // RegisterUser is validated before, so both are set.
        $email = $registration->email ?? throw new \LogicException('RegisterUser without an email');
        $password = $registration->password ?? throw new \LogicException('RegisterUser without a password');

        // Lower case, so an address has one account however it is typed.
        $email = mb_strtolower(trim($email));
        if ($this->userRepository->emailExists($email)) {
            throw new ConflictHttpException(self::EMAIL_TAKEN_MSG);
        }

        $user = (new User())->setEmail($email)->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        try {
            $this->userRepository->create($user);
        } catch (UniqueConstraintViolationException $e) {
            // Registered by another request between the check and the insert.
            throw new ConflictHttpException(self::EMAIL_TAKEN_MSG, $e);
        }

        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], Response::HTTP_CREATED);
    }
}
