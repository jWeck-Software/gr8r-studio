<?php

namespace App\Sync\Handler;

use App\Access\WorkspaceAccess;
use App\Entity\Member;
use App\Entity\Workspace;
use App\Sync\Fields;
use App\Sync\SingletonHandler;
use App\Sync\SyncRejected;
use Doctrine\ORM\EntityManagerInterface;

/** The Workspace's name, URL and colour (`ws`), changed by Owners and Admins. */
final readonly class WorkspaceSettingsHandler implements SingletonHandler
{
    /** The frontend's slug() makes URLs like this from what is typed. */
    private const string URL_FORMAT = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceAccess $access,
    ) {
    }

    public function type(): string
    {
        return 'ws';
    }

    public function set(Member $actor, mixed $data): void
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw SyncRejected::invalid('The Workspace settings must be {name, url, c, brand}.');
        }
        $f = new Fields($data, 'Workspace');
        $name = $f->nonEmptyString('name', 120);
        $url = $f->string('url', 120);
        if (1 !== preg_match(self::URL_FORMAT, $url)) {
            throw SyncRejected::invalid('The Workspace URL must be lowercase letters and digits, with single hyphens between them, like gr8r-studio.');
        }
        $c = $f->colour('c');
        $brand = $f->bool('brand');
        if (!$this->access->manages($actor)) {
            throw SyncRejected::forbidden('Only Owners and Admins change the Workspace\'s settings.');
        }
        $ws = $actor->getWorkspace();
        $owner = $this->em->getRepository(Workspace::class)->findOneBy(['url' => $url]);
        if (null !== $owner && $owner !== $ws) {
            throw SyncRejected::invalid(\sprintf('The Workspace URL "%s" is taken.', $url));
        }
        $ws->rename($name, $url, $c, $brand);
    }
}
