<?php

namespace App\Service;

use App\Entity\Tool;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * /api/analytics/department-costs
     */
    public function getDepartmentCosts(?string $sortBy, string $order): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t.owner_department AS department')
            ->addSelect('SUM(t.monthly_cost) AS totalCost')
            ->addSelect('COUNT(t.id) AS toolsCount')
            ->addSelect('SUM(t.active_users_count) AS totalUsers')
            ->from(Tool::class, 't')
            ->where('t.status = :active')
            ->setParameter('active', 'active')
            ->groupBy('t.owner_department');

        $rows = $qb->getQuery()->getArrayResult();

        $totalCompanyCost = 0.0;
        foreach ($rows as $row) {
            $totalCompanyCost += $row['totalCost'];
        }

        $data = [];
        foreach ($rows as $row) {
            $departementTotal = $row['totalCost'];
            $toolsCount = $row['toolsCount'];
            $totalUsers = $row['totalUsers'];

            // On calcule le coût moyen par outil et le pourcentage par rapport au budget total
            $avgCostPerTool = $toolsCount > 0 ? $this->roundValue($departementTotal / $toolsCount) : 0.0; // On met ": 0.0" pour eviter la division par 0
            $costPercentage = $totalCompanyCost > 0 ? $this->roundPercent(($departementTotal / $totalCompanyCost) * 100) : 0.0; // On met ": 0.0" pour eviter la division par 0

            $data[] = [
                'department' => $row['department'],
                'total_cost' => $this->roundValue($departementTotal),
                'tools_count' => $toolsCount,
                'total_users' => $totalUsers,
                'average_cost_per_tool' => $avgCostPerTool,
                'cost_percentage' => $costPercentage,
            ];
        }

        // Tri simple
        $sortBy = $sortBy ?: 'total_cost';
        $order = strtolower($order) === 'asc' ? SORT_ASC : SORT_DESC;

        // On extrait la colonne à trier puis on trie ce tableau et réorganise $data en même temps
        $col = array_column($data, $sortBy);
        if (!empty($data))
            array_multisort($col, $order, $data);

        // Trouver le département le plus cher
        $mostExp = null;
        $maxCost = -1.0;
        foreach ($data as $d) {
            if ($d['total_cost'] > $maxCost) {
                $maxCost = $d['total_cost'];
                $mostExp = $d['department'];
            }
        }

        return [
            'data' => $data,
            'summary' => [
                'total_company_cost' => $this->roundValue($totalCompanyCost),
                'departments_count' => count($data),
                'most_expensive_department' => $mostExp,
            ],
        ];
    }

    /**
     * /api/analytics/expensive-tools
     */
    public function getExpensiveTools(?float $minCost, int $limit): array
    {
        $avgCostPerUser = $this->getCompanyAvgCostPerUser();

        $qb = $this->em->createQueryBuilder()
            ->select('t.id, t.name AS name, t.monthly_cost AS monthlyCost, t.active_users_count AS activeUsersCount, t.owner_department AS department, t.vendor AS vendor')
            ->from(Tool::class, 't')
            ->where('t.status = :active')
            ->setParameter('active', 'active')
            ->orderBy('t.monthly_cost', 'DESC');

        if ($minCost !== null) {
            $qb->andWhere('t.monthly_cost >= :minCost')->setParameter('minCost', $minCost);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $potentialSavings = 0.0;
        $list = [];

        foreach ($rows as $row) {
            $monthlyCost = $row['monthlyCost'];
            $users = $row['activeUsersCount'];

            $costPerUser = $users > 0 ? ($monthlyCost / $users) : $monthlyCost; // Si 0 users, costPerUser = monthlyCost
            $rating = $this->efficiencyRating($costPerUser, $avgCostPerUser);

            if ($rating === 'low') {
                $potentialSavings += $monthlyCost;
            }

            $list[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'monthly_cost' => $this->roundValue($monthlyCost),
                'active_users_count' => $users,
                'cost_per_user' => $this->roundValue($costPerUser),
                'department' => $row['department'],
                'vendor' => $row['vendor'],
                'efficiency_rating' => $rating,
            ];
        }

        // Limiter le nombre d’éléments renvoyés
        $data = array_slice($list, 0, $limit);

        return [
            'data' => $data,
            'analysis' => [
                'total_tools_analyzed'  => count($rows),
                'avg_cost_per_user_company' => $this->roundValue($avgCostPerUser),
                'potential_savings_identified' => $this->roundValue($potentialSavings),
            ],
        ];
    }

    /**
     * /api/analytics/tools-by-category
     */
    public function getToolsByCategory(): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c.name AS categoryName')
            ->addSelect('COUNT(t.id) AS toolsCount')
            ->addSelect('SUM(t.monthly_cost) AS totalCost')
            ->addSelect('SUM(t.active_users_count) AS totalUsers')
            ->from(Tool::class, 't')
            ->join('t.category', 'c')
            ->where('t.status = :active')
            ->setParameter('active', 'active')
            ->groupBy('c.id');

        $rows = $qb->getQuery()->getArrayResult();

        $totalCompanyCost = 0.0;
        foreach ($rows as $row) {
            $totalCompanyCost += $row['totalCost'];
        }

        $data = [];
        foreach ($rows as $row) {
            $totalCost  = $row['totalCost'];
            $users = $row['totalUsers'];

            // On met ": 0.0" pour eviter la division par 0
            $percentageOfBudget= $totalCompanyCost > 0 ? $this->roundPercent(($totalCost / $totalCompanyCost) * 100) : 0.0;
            $averageCostPerUser = $users > 0 ? $this->roundValue($totalCost / $users) : 0.0;

            $data[] = [
                'category_name' => $row['categoryName'],
                'tools_count' => $row['toolsCount'],
                'total_cost' => $this->roundValue($totalCost),
                'total_users' => $users,
                'percentage_of_budget' => $percentageOfBudget,
                'average_cost_per_user' => $averageCostPerUser,
            ];
        }

        // Trouver la catégorie la plus chère et la plus efficace
        $mostExpensive = null;
        $maxCost = -1.0;
        $mostEfficient = null;
        $minAverageCostPerUser = null;

        foreach ($data as $d) {
            //  Trouver le most expensive category
            if ($d['total_cost'] > $maxCost) {
                $maxCost = $d['total_cost'];
                $mostExpensive = $d['category_name'];
            }
            //  Trouver le most efficient category
            if ($d['total_users'] > 0) {
                if ($minAverageCostPerUser === null || $d['average_cost_per_user'] < $minAverageCostPerUser) {
                    $minAverageCostPerUser = $d['average_cost_per_user'];
                    $mostEfficient = $d['category_name'];
                }
            }
        }

        return [
            'data' => $data,
            'insights' => [
                'most_expensive_category' => $mostExpensive,
                'most_efficient_category' => $mostEfficient,
            ],
        ];
    }

    /**
     * /api/analytics/low-usage-tools
     */
    public function getLowUsageTools(int $maxUsers): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t.id, t.name AS name, t.monthly_cost AS monthlyCost, t.active_users_count AS activeUsersCount, t.owner_department AS department, t.vendor AS vendor')
            ->from(Tool::class, 't')
            ->where('t.status = :active')
            ->andWhere('t.active_users_count <= :max')
            ->setParameter('active', 'active')
            ->setParameter('max', $maxUsers)
            ->orderBy('t.active_users_count', 'ASC')
            ->addOrderBy('t.monthly_cost', 'DESC'); // On ordonne de façon à voir en premier les cost_per_user les plus élevés

        $rows = $qb->getQuery()->getArrayResult();

        $data = [];
        $potentialMonthly = 0.0;

        foreach ($rows as $row) {
            $monthlyCost = $row['monthlyCost'];
            $users = $row['activeUsersCount'];

            $costPerUser = $users > 0 ? ($monthlyCost / $users) : $monthlyCost;
            $warning = $this->warningLevel($users, $costPerUser);
            $action  = $this->potentialAction($warning);

            if ($warning === 'high' || $warning === 'medium') {
                $potentialMonthly += $monthlyCost;
            }

            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'monthly_cost' => $this->roundValue($monthlyCost),
                'active_users_count' => $users,
                'cost_per_user' => $this->roundValue($costPerUser),
                'department' => $row['department'],
                'vendor' => $row['vendor'],
                'warning_level' => $warning,
                'potential_action' => $action,
            ];
        }

        return [
            'data' => $data,
            'savings_analysis' => [
                'total_underutilized_tools' => count($data),
                'potential_monthly_savings' => $this->roundValue($potentialMonthly),
                'potential_annual_savings'  => $this->roundValue($potentialMonthly * 12),
            ],
        ];
    }

    /**
     * /api/analytics/vendor-summary
     */
    public function getVendorSummary(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('t.monthly_cost AS monthlyCost,t.active_users_count AS users,t.owner_department AS department, t.vendor AS vendor ')
            ->from(Tool::class, 't')
            ->where('t.status = :active')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getArrayResult();

        // Agrégations par vendor
        $byVendor = [];
        foreach ($rows as $row) {
            $vendor = (string) $row['vendor'];
            if (!isset($byVendor[$vendor])) {
                $byVendor[$vendor] = [
                    'tools_count' => 0,
                    'total_monthly_cost' => 0.0,
                    'total_users' => 0,
                    'departments' => [],
                ];
            }

            $byVendor[$vendor]['tools_count'] += 1; // On compte les outils
            $byVendor[$vendor]['total_monthly_cost'] += $row['monthlyCost']; // on additionne les coûts
            $byVendor[$vendor]['total_users'] += $row['users'];    // on additionne les utilisateurs
            $byVendor[$vendor]['departments'][] = $row['department']; // on stocke les départements distincts
        }

        $data = [];
        foreach ($byVendor as $vendor => $aggregation) {

            // On récupère le tableau des différents départements associés au vendor
            $departements = array_values(array_unique($aggregation['departments']));
            sort($departements);


            $users  = $aggregation['total_users'];
            $avgCostPerUser = $users > 0 ? $this->roundValue($aggregation['total_monthly_cost'] / $users) : 0.0;

            $data[] = [
                'vendor' => $vendor,
                'tools_count' => $aggregation['tools_count'],
                'total_monthly_cost' => $this->roundValue($aggregation['total_monthly_cost']),
                'total_users' => $users,
                'departments' => implode(',', $departements),
                'average_cost_per_user' => $avgCostPerUser,
                'vendor_efficiency' => $this->vendorEfficiency($avgCostPerUser),
            ];
        }

        // Vendor insights
        $mostExpensiveVendor = null;
        $maxCost = -1.0;
        $mostEfficientVendor = null;
        $minCostPerUser = null;
        $singleToolVendors = 0;

        foreach ($data as $d) {
            //  Trouver le most expensive vendor
            if ($d['total_monthly_cost'] > $maxCost) {
                $maxCost = $d['total_monthly_cost'];
                $mostExpensiveVendor = $d['vendor'];
            }
            // Nombre de single tool vendors
            if ($d['tools_count'] === 1) {
                $singleToolVendors++;
            }
            // Trouver le most efficient vendor
            if ($d['total_users'] > 0) {
                if ($minCostPerUser === null || $d['average_cost_per_user'] < $minCostPerUser) {
                    $minCostPerUser = $d['average_cost_per_user'];
                    $mostEfficientVendor = $d['vendor'];
                }
            }
        }

        // Tri des vendor par ordre alphabétique
        $col = array_column($data, 'vendor');
        array_multisort($col, SORT_ASC, $data);

        return [
            'data' => $data,
            'vendor_insights' => [
                'most_expensive_vendor' => $mostExpensiveVendor,
                'most_efficient_vendor' => $mostEfficientVendor,
                'single_tool_vendors'   => $singleToolVendors,
            ],
        ];
    }

    // Helpers

    private function getCompanyAvgCostPerUser(): float
    {
        $res = $this->em->createQueryBuilder()
            ->select('SUM(t.monthly_cost) AS totalCost')
            ->addSelect('SUM(CASE WHEN t.active_users_count > 0 THEN t.active_users_count ELSE 0 END) AS totalUsers')
            ->from(Tool::class, 't')
            ->where('t.status = :active')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleResult();

        $totalCost = $res['totalCost'] ?? 0.0;
        $totalUsers = $res['totalUsers'] ?? 0.0;

        return $totalUsers > 0 ? ($totalCost / $totalUsers) : 0.0;
    }

    private function efficiencyRating(float $costPerUser, float $avgCostPerUser): string
    {
        if ($avgCostPerUser <= 0.0) {
            return 'average';
        }
        $ratio = $costPerUser / $avgCostPerUser;
        if ($ratio < 0.5)
            return 'excellent';
        if ($ratio < 0.8)
            return 'good';
        if ($ratio <= 1.2)
            return 'average';
        return 'low';
    }

    private function warningLevel(int $users, float $costPerUser): string
    {
        if ($users === 0)
            return 'high';
        if ($costPerUser < 20.0)
            return 'low';
        if ($costPerUser <= 50.0)
            return 'medium';
        return 'high';
    }

    private function potentialAction(string $warning): string
    {
        if ($warning === 'high')
            return 'Consider canceling or downgrading';
        if ($warning === 'medium')
            return 'Review usage and consider optimization';
        return 'Monitor usage trends';
    }

    private function vendorEfficiency(float $avgCostPerUser): string
    {
        if ($avgCostPerUser < 5.0)
            return 'excellent';
        if ($avgCostPerUser <= 15.0)
            return 'good';
        if ($avgCostPerUser <= 25.0)
            return 'average';
        return 'poor';
    }

    private function roundValue(float $value): float {
        return round($value, 2);
    }
    private function roundPercent(float $v): float {
        return round($v, 1);
    }

}
