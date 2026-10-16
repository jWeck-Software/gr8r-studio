<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Member> */
final class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    /** The Member a signed-in User acts as. Switching Workspaces is out of scope, so it is their only one. */
    public function forUser(User $user): ?Member
    {
        return $this->findOneBy(['user' => $user]);
    }
}
