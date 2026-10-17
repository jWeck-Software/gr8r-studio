<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Email;

/**
 * In dev, emails go nowhere (MAILER_DSN=null://null): each sent one is written as a line to
 * var/log/mail.log instead, with its recipients, its subject and the links it contains, so a
 * verification, password reset or Invite link can be opened from there.
 */
#[When(env: 'dev')]
#[AsEventListener]
final readonly class MailLogListener
{
    public function __construct(
        #[Autowire('%kernel.logs_dir%/mail.log')]
        private string $path,
    ) {
    }

    public function __invoke(SentMessageEvent $event): void
    {
        $email = $event->getMessage()->getOriginalMessage();
        if (!$email instanceof Email) {
            return;
        }
        $body = implode(' ', array_filter([$email->getTextBody(), $email->getHtmlBody()], is_string(...)));
        preg_match_all('~https?://[^\s<>"\']+~', $body, $links);
        $line = \sprintf(
            "%s to=%s subject=%s links=%s\n",
            date(\DATE_ATOM),
            implode(',', array_map(static fn ($address) => $address->getAddress(), $email->getTo())),
            json_encode($email->getSubject(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            implode(' ', array_unique($links[0])),
        );
        if (!is_dir(\dirname($this->path))) {
            mkdir(\dirname($this->path), 0o777, true);
        }
        file_put_contents($this->path, $line, \FILE_APPEND | \LOCK_EX);
    }
}
