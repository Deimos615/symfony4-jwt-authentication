<?php
namespace App\Service;

use Firebase\JWT\JWT;

class JwtTokenGenerator
{
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function generateToken($user): string
    {
        $payload = [
            // Customize the token payload with user-specific data.
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'avatar' => $user->getAvatar(),
            'active' => $user->getActive(),
            'roles' => $user->getRoles(),
            'exp' => time() + 3600, // Token expiration time (1 hour in this example).
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }
}