<?php

namespace App\Repository;

use App\Entity\Tool;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class ToolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Tool::class);
    }

    /** @return array{items: array<int, Tool>, total:int, filtered:int} */
    public function search(array $filters): array
    {
        // Total global
        $total = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // On filtre les tools et category
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c');

        $this->applyFilters($qb, $filters);

        // Tri
        $tri = [
            'name'         => 't.name',
            'monthly_cost' => 't.monthly_cost',
            'created_at'   => 't.created_at',
        ];

        // On regarde si le client a envoyé sort_by
        $sortBy  = $filters['sort_by']  ?? 'name';
        $sortCol = $tri[$sortBy] ?? 't.name';

        // On regarde si le client a envoyé sort_dir
        $sortDir = strtoupper($filters['sort_dir'] ?? 'ASC');
        if (!in_array($sortDir, ['ASC', 'DESC'])) {
            $sortDir = 'ASC';
        }
        $qb->orderBy($sortCol, $sortDir);

        $items = $qb->getQuery()->getResult();

        // Total filtré
        $qbCount = $this->createQueryBuilder('t');

        $this->applyFilters($qbCount, $filters);

        $filtered = $qbCount
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'items'    => $items,
            'total'    => $total,
            'filtered' => $filtered,
        ];
    }

    // On applique les filtres
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['department'])) {
            $qb->andWhere('t.owner_department = :dep')
                ->setParameter('dep', $filters['department']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :st')
                ->setParameter('st', $filters['status']);
        }

        if (isset($filters['min_cost']) && $filters['min_cost'] !== '') {
            $qb->andWhere('t.monthly_cost >= :minc')
                ->setParameter('minc', (float)$filters['min_cost']);
        }

        if (isset($filters['max_cost']) && $filters['max_cost'] !== '') {
            $qb->andWhere('t.monthly_cost <= :maxc')
                ->setParameter('maxc', (float)$filters['max_cost']);
        }

        if (!empty($filters['category'])) {

            // Pour calculer le total filtré, on refait un leftjoin pour filtrer sur la category
            $qb->leftJoin('t.category', 'c2');

            if (is_numeric($filters['category'])) {
                $qb->andWhere('c2.id = :catId')->setParameter('catId', (int)$filters['category']);
            } else {
                $qb->andWhere('c2.name = :catName')->setParameter('catName', $filters['category']);
            }
        }
    }

}
