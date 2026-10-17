<?php

namespace App\Tests\Mail;

use App\Mail\Emails;
use App\Persistence\Transaction;
use App\Token\Purpose;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** The links the app's emails carry, how long they say a link works, and how they are sent. */
final class EmailsTest extends TestCase
{
    public function testALinkLeadsToTheFrontendsPageForItsPurpose(): void
    {
        $emails = $this->emails('https://studio.example/app/');

        self::assertSame('https://studio.example/app/#join/abc', $emails->link(Purpose::Invite, 'abc'));
        self::assertSame('https://studio.example/app/#verify/abc', $emails->link(Purpose::VerifyEmail, 'abc'));
        self::assertSame('https://studio.example/app/#reset/abc', $emails->link(Purpose::ResetPassword, 'abc'));
        self::assertSame('https://studio.example/app/#login', $emails->page('login'));
    }

    public function testTheLifetimeInWordsIsHowLongTheTokenWorks(): void
    {
        self::assertSame('7 days', Emails::lifetime(Purpose::Invite));
        self::assertSame('24 hours', Emails::lifetime(Purpose::VerifyEmail));
        self::assertSame('30 minutes', Emails::lifetime(Purpose::ResetPassword));
    }

    public function testAnEmailAfterTheCommitThatCannotBeSentIsLogged(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('The email "{subject}" could not be sent: {reason}', ['subject' => 'Hello', 'reason' => 'down']);
        $transaction = $this->transaction();
        $emails = new Emails($mailer, $transaction, $logger, 'https://studio.example');

        $transaction->run(fn () => $emails->sendAfterCommit((new Email())->to('a@example.test')->subject('Hello')->text('Hi')));
    }

    public function testTheFailureIsLoggedWithTheCallersMessageAndContext(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('The Invite email to {member} could not be sent: {reason}', ['member' => 'm8', 'subject' => 'Hello', 'reason' => 'down']);
        $transaction = $this->transaction();
        $emails = new Emails($mailer, $transaction, $logger, 'https://studio.example');

        $transaction->run(fn () => $emails->sendAfterCommit((new Email())->to('a@example.test')->subject('Hello')->text('Hi'), 'The Invite email to {member} could not be sent: {reason}', ['member' => 'm8']));
    }

    public function testAnEmailSentNowThatCannotBeSentReachesTheCaller(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('down'));
        $emails = new Emails($mailer, $this->transaction(), $this->createStub(LoggerInterface::class), 'https://studio.example');

        $this->expectException(TransportException::class);
        $emails->send((new Email())->to('a@example.test')->subject('Hello')->text('Hi'));
    }

    private function emails(string $frontendUrl): Emails
    {
        return new Emails($this->createStub(MailerInterface::class), $this->transaction(), $this->createStub(LoggerInterface::class), $frontendUrl);
    }

    private function transaction(): Transaction
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (\Closure $work) => $work());

        return new Transaction($em);
    }
}
