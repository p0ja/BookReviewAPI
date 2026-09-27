<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterUser;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication')]
final class RegistrationController extends AbstractController
{
    private const EMAIL_TAKEN_MSG = 'An account with this email already exists';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
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
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterUser $registration): Response
    {
        $email = trim($registration->email);
        if ($this->userRepository->emailExists($email)) {
            throw new ConflictHttpException(self::EMAIL_TAKEN_MSG);
        }

        $user = (new User())->setEmail($email)->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $registration->password));

        try {
            $this->userRepository->create($user);
        } catch (UniqueConstraintViolationException $e) {
            // Registered by another request between the check and the insert.
            throw new ConflictHttpException(self::EMAIL_TAKEN_MSG, $e);
        }

        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], Response::HTTP_CREATED);
    }
}
