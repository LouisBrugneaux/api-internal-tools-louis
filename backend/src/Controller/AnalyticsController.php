<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/analytics')]
class AnalyticsController
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}
    #[Route('/department-costs', name: 'analytics_department_costs', methods: ['GET'])]
    public function departmentCosts(Request $request): JsonResponse
    {
        $sortBy = $request->query->get('sort_by');
        $order = strtolower($request->query->get('order', 'desc'));

        // Gestion des erreurs
        if ($sortBy && !in_array($sortBy, ['total_cost','department','tools_count','total_users','average_cost_per_tool','cost_percentage']))
            return $this->badRequestMessage('sort_by', 'Must be one of: total_cost, department, tools_count, total_users, average_cost_per_tool, cost_percentage');

        if (!in_array($order, ['asc','desc']))
            return $this->badRequestMessage('order', 'Must be asc or desc');

        $res = $this->analyticsService->getDepartmentCosts($sortBy, $order);
        if ($res['summary']['total_company_cost'] === 0.0)
            $res['message'] = 'No analytics data available - ensure tools data exists';

        return new JsonResponse($res, 200);
    }


    #[Route('/expensive-tools', name: 'analytics_expensive_tools', methods: ['GET'])]
    public function expensiveTools(Request $request): JsonResponse
    {
        $limit = $request->query->get('limit', 10);
        $minCost = $request->query->get('min_cost');

        if ($limit < 1 || $limit > 100)
            return $this->badRequestMessage('limit', 'Must be positive integer between 1 and 100');

        if ($minCost !== null && $minCost < 0)
            return $this->badRequestMessage('min_cost', 'Must be >= 0');

        return new JsonResponse($this->analyticsService->getExpensiveTools($minCost, $limit));
    }


    #[Route('/tools-by-category', name: 'analytics_tools_by_category', methods: ['GET'])]
    public function toolsByCategory(): JsonResponse
    {
        $res = $this->analyticsService->getToolsByCategory();
        if (empty($res['data']))
            $res['message'] = 'No analytics data available - ensure tools data exists';

        return new JsonResponse($res, 200);
    }


    #[Route('/low-usage-tools', name: 'analytics_low_usage_tools', methods: ['GET'])]
    public function lowUsageTools(Request $request): JsonResponse
    {
        $max = $request->query->get('max_users', 5);
        if ($max < 0)
            return $this->badRequestMessage('max_users', 'Must be a non-negative integer');

        return new JsonResponse($this->analyticsService->getLowUsageTools($max), 200);
    }


    #[Route('/vendor-summary', name: 'analytics_vendor_summary', methods: ['GET'])]
    public function vendorSummary(): JsonResponse
    {
        $res = $this->analyticsService->getVendorSummary();
        if (empty($res['data']))
            $res['message'] = 'No analytics data available - ensure tools data exists';

        return new JsonResponse($res, 200);
    }

    private function notFound(int $id): JsonResponse
    {
        return new JsonResponse(['error' => 'Tool not found', 'message' => "Tool with ID $id does not exist"], 404);
    }

    private function badRequestMessage(string $field, string $msg): JsonResponse
    {
        return new JsonResponse(['error'=>'Validation failed','details'=>[$field=>$msg]], 400);
    }
}
