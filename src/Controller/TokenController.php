<?php

namespace App\Controller;

use App\Entity\User;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class TokenController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/users', name: 'rest_users', methods: ['GET'])]
    public function list(): Response
    {
        return $this->json($this->userRepository->findAll());
    }

    #[Route('/user/{id}', name: 'rest_user', methods: ['GET'])]
    public function index(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            $this->logger->log(NamespaceEnum::REST_USER->value, 'User not found');

            throw $this->createNotFoundException('User not found');
        }

        return $this->json($user);
    }

    #[Route('/register', name: 'add_user', methods: ['POST'])]
    public function create(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $decoded = json_decode($request->getContent());
        $email = $decoded->email;
        $plaintextPassword = $decoded->password;

        $user = new User();
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );
        $user->setPassword($hashedPassword);
        $user->setEmail($email);

        $this->userRepository->create($user);

        return $this->json(['message' => 'Registered Successfully']);

    }
}
