<?php

namespace App\Access;

use App\Entity\Member;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Who may see and change which Projects, and with them everything inside a Project.
 *
 * A Private project is seen only by its own Members, whatever their Role. A Guest sees only
 * the Projects they belong to and changes none of them. The snapshot and the sync handlers
 * both ask here, so the rules live in one place (ADR 0003: visibility is server-owned).
 */
final readonly class ProjectAccess
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return list<Project> the Projects of the Member's Workspace the Member may see */
    public function visibleProjects(Member $member): array
    {
        $projects = $this->em->getRepository(Project::class)->findBy(['workspace' => $member->getWorkspace()]);

        return array_values(array_filter($projects, fn (Project $p): bool => $this->sees($member, $p)));
    }

    public function sees(Member $member, Project $project): bool
    {
        if ($project->getWorkspace() !== $member->getWorkspace()) {
            return false;
        }
        if ($member->isGuest() || $project->isPrivate()) {
            return \in_array($member->getId(), $project->getMembers(), true);
        }

        return true;
    }

    /**
     * Whether the Member may change the Project and its Tasks; with null, whether the Member may
     * create a Project or change the Project order. Starring is not a change: whoever sees a
     * record may star it.
     */
    public function mayChange(Member $member, ?Project $project = null): bool
    {
        return !$member->isGuest() && (null === $project || $this->sees($member, $project));
    }
}
