<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class SendNewsletterCommand extends Command
{
    protected static $defaultName = 'app:send-newsletter';

    private $entityManager;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this->setDescription('Send newsletter to all active users created in the last week.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $subject = 'Your best newsletter';
        $message = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec id interdum nibh. Phasellus blandit tortor in cursus convallis. Praesent et tellus fermentum, pellentesque lectus at, tincidunt risus. Quisque in nisl malesuada, aliquet nibh at, molestie libero.';

        $users = $this->entityManager->getRepository(User::class)->findActiveUsersCreatedLastWeek();

        foreach ($users as $user) {
            $email = (new Email())
                ->from(new Address('newsletter@example.com', 'Cobbleweb'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject($subject)
                ->text($message);

            $this->mailer->send($email);
        }

        $output->writeln('Newsletter sent to ' . count($users) . ' users.');

        return true;
    }
}
