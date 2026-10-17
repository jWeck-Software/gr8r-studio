<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** The User with this email address, in any case. */
    public function withEmail(string $email): ?User
    {
        return $this->getEntityManager()->createQuery('SELECT u FROM '.User::class.' u WHERE LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower($email))
            ->getOneOrNullResult();
    }
}
