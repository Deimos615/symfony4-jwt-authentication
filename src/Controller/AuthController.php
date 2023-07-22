<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use App\Repository\PhotoRepository;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Security\TokenAuthenticator;
use Aws\S3\S3Client;
use App\Service\AwsS3Uploader;

/**
 * @Route("/api/users", name="app_users_")
 */
class AuthController extends AbstractController
{

    private $userRepository;
    private $photoRepository;
    private $userPasswordEncoder;
    private $JWTTokenManager;
    private $security;
    private $guardAuthenticatorHandler;
    private $tokenAuthenticator;
    public function __construct(UserRepository $userRepository, PhotoRepository $photoRepository, UserPasswordEncoderInterface $userPasswordEncoder, JWTTokenManagerInterface $JWTTokenManager, Security $security, GuardAuthenticatorHandler $guardAuthenticatorHandler, TokenAuthenticator $tokenAuthenticator)
    {
        $this->userRepository = $userRepository;
        $this->photoRepository = $photoRepository;
        $this->userPasswordEncoder = $userPasswordEncoder;
        $this->JWTTokenManager = $JWTTokenManager;
        $this->security = $security;
        $this->guardAuthenticatorHandler = $guardAuthenticatorHandler;
        $this->tokenAuthenticator = $tokenAuthenticator;
    }
    /**
     * @Route("/register", name="register", methods="POST")
     */
    public function register(Request $request, AwsS3Uploader $awsS3Uploader): Response
    {
        $email = $request->request->get('email');
        $is_exist = $this->userRepository->findOneBy(['email' => $email]);
        // confirm if email exist
        if (!!$is_exist) {
            return $this->json([
                'success' => false,
                'msg' => 'Email already Exist!',
            ]);
        }
        /*photos upload
        uploaded photo key must be like photo1, photo2, photo3, photo4*/
        $user = new User();
        $photo1 = $request->files->get('photo1');
        $photo2 = $request->files->get('photo2');
        $photo3 = $request->files->get('photo3');
        $photo4 = $request->files->get('photo4');
        if ($photo1 && $photo2 && $photo3 && $photo4) {
            $photo_1 = new Photo();
            $photoName1 = $this->generateUniqueFileName().'.'.$photo1->guessExtension();
            $photo1->move(
                $this->getParameter('photos_directory'),
                $photoName1
            );
            $awsS3Uploader->uploadUserPhoto($photo1, $photoName1); // upload photo to AWS
            $photo_1->setName($photo1->getClientOriginalName());
            $photo_1->setUrl($photoName1);
            $this->photoRepository->add($photo_1);
            $photo_2 = new Photo();
            $photoName2 = $this->generateUniqueFileName().'.'.$photo2->guessExtension();
            $photo2->move(
                $this->getParameter('photos_directory'),
                $photoName2
            );
            $awsS3Uploader->uploadUserPhoto($photo2, $photoName2);
            $photo_2->setName($photo2->getClientOriginalName());
            $photo_2->setUrl($photoName2);
            $this->photoRepository->add($photo_2);
            $photo_3 = new Photo();
            $photoName3 = $this->generateUniqueFileName().'.'.$photo3->guessExtension();
            $photo3->move(
                $this->getParameter('photos_directory'),
                $photoName3
            );
            $awsS3Uploader->uploadUserPhoto($photo3, $photoName3);
            $photo_3->setName($photo3->getClientOriginalName());
            $photo_3->setUrl($photoName3);
            $this->photoRepository->add($photo_3);
            $photo_4 = new Photo();
            $photoName4 = $this->generateUniqueFileName().'.'.$photo4->guessExtension();
            $photo4->move(
                $this->getParameter('photos_directory'),
                $photoName4
            );
            $awsS3Uploader->uploadUserPhoto($photo4, $photoName4);
            $photo_4->setName($photo4->getClientOriginalName());
            $photo_4->setUrl($photoName4);
            $this->photoRepository->add($photo_4);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'You must upload at least 4 photos.',
            ]);
        }
        $avatar = $request->files->get('avatar');
        if ($avatar) {
            $fileName = $this->generateUniqueFileName().'.'.$avatar->guessExtension();
            $avatar->move(
                $this->getParameter('avatar_directory'),
                $fileName
            );
            $user->setAvatar($fileName);
        } else {
            $user->setAvatar($this->getParameter('default_avatar'));
        }
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $hashedPassword = $this->userPasswordEncoder->encodePassword($user, $password);
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setRoles(['ROLE_USER']);
        $user->setLastName($lastName);
        $user->setFullName();
        $user->setPassword($hashedPassword);
        // add photos to user
        $user->addPhoto($photo_1);
        $user->addPhoto($photo_2);
        $user->addPhoto($photo_3);
        $user->addPhoto($photo_4);
        $this->userRepository->add($user);
        return $this->json([
            'success' => true,
            'msg' => 'User registration success!',
            'user' => $user // App\Entity\User
        ]);
    }

    /**
     * @Route("/login", name="login", methods="POST")
     */
    public function login(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        // verify user and password
        if (!$user) {
            return $this->json(['success' => false, 'msg' => 'Invalid credentials']);
        } else if (!$this->userPasswordEncoder->isPasswordValid($user, $password)) {
            return $this->json(['success' => false, 'msg' => 'Password Incorrect!']);
        }

        // Generate the JWT token
        $token = $this->JWTTokenManager->create($user);
        // login handler
        $this->guardAuthenticatorHandler->authenticateUserAndHandleSuccess($user, $request, $this->tokenAuthenticator, 'main');
        // update user by api_token
        $user->setApiToken($token);
        $this->userRepository->add($user);
        return $this->json([
            'success' => true,
            'token' => $token,
            'user' => $this->getUser(),
        ]);
    }

    /**
     * @Route("/me", name="_me", methods="GET")
     */
    public function myInfo(): Response
    {
        // get user by authorization
        $user = $this->getUser();
        if ($user) {
            return $this->json([
                'user' => $user,
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
            ]);
        } else {
            return $this->json([
                'success' => false,
                'msg' => 'There isn`t authorization user.'
            ]);
        }
    }

    /**
     * @return string
     */
    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }
}
