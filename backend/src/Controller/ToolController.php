<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Tool;
use App\Repository\ToolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

#[Route('/api/tools')]
class ToolController
{
    // On fait les enum sur les departements et status
    private const DEPARTMENTS = ['Engineering','Sales','Marketing','HR','Finance','Operations','Design'];
    private const STATUSES    = ['active','deprecated','trial'];

    #[Route('', name: 'tools_list', methods: ['GET'])]
    public function list(Request $request, ToolRepository $repo): JsonResponse
    {
        // Tableau des filtres qui peuvent être envoyés en entrée
        $filters = [
            'department' => $request->query->get('department'),
            'status'     => $request->query->get('status'),
            'min_cost'   => $request->query->get('min_cost'),
            'max_cost'   => $request->query->get('max_cost'),
            'category'   => $request->query->get('category'),
            'sort_by'    => $request->query->get('sort_by', 'name'),
            'sort_dir'   => $request->query->get('sort_dir', 'ASC'),
        ];

        // On sort les tools qui correspondent aux filtres
        $res = $repo->search($filters);

        // On rentre toutes les données souhaitées dans la variable $data
        $data = array_map(function(Tool $tool){
            return [
                'id' => $tool->getId(),
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'vendor' => $tool->getVendor(),
                'website_url' => $tool->getWebsiteUrl(),
                'category' => $tool->getCategory()?->getName(),
                'monthly_cost' => (float)$tool->getMonthlyCost(),
                'owner_department' => $tool->getOwnerDepartment(),
                'status' => $tool->getStatus(),
                'active_users_count' => $tool->getActiveUsersCount(),
                'created_at' => $tool->getCreatedAt()?->format('Y-m-d\TH:i:s\Z'),
            ];
        }, $res['items']);

        // Ensemble des filtres appliqués
        $filtersApplied = [];
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $filtersApplied[$key] = $value;
            }
        }

        return new JsonResponse([
            'data' => $data,
            'total' => $res['total'],
            'filtered' => $res['filtered'],
            'filters_applied' => $filtersApplied,
        ], 200);
    }

    #[Route('/{id}', name: 'tools_detail', methods: ['GET'])]
    public function detail(int $id, ToolRepository $repo): JsonResponse
    {
        $tool = $repo->find($id);
        if (!$tool)
            return $this->notFound($id);

        $totalMonthlyCost = $tool->getMonthlyCost() * $tool->getActiveUsersCount();

        return new JsonResponse([
            'id' => $tool->getId(),
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'vendor' => $tool->getVendor(),
            'website_url' => $tool->getWebsiteUrl(),
            'category' => $tool->getCategory()->getName(),
            'monthly_cost' => $tool->getMonthlyCost(),
            'owner_department' => $tool->getOwnerDepartment(),
            'status' => $tool->getStatus(),
            'active_users_count' => $tool->getActiveUsersCount(),
            'total_monthly_cost' => $totalMonthlyCost,
            'created_at' => $tool->getCreatedAt()?->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $tool->getUpdatedAt()?->format('Y-m-d\TH:i:s\Z'),
        ], 200);
    }

    #[Route('', name: 'tools_create', methods: ['POST'])]
    public function create(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($req->getContent(), true) ?? [];

        // Validations
        $validator = Validation::createValidator();
        $violations = $validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(min:2,max:100)],
            'description' => new Assert\Optional([new Assert\Type('string')]),
            'vendor' => [new Assert\NotBlank(), new Assert\Length(max:100)],
            'website_url' => new Assert\Optional([new Assert\Url()]),
            'category_id' => [new Assert\NotBlank(), new Assert\Type('integer')],
            'monthly_cost' => [new Assert\NotNull(), new Assert\PositiveOrZero()],
            'owner_department' => [new Assert\NotBlank(), new Assert\Choice(self::DEPARTMENTS)],
        ], allowExtraFields: true));

        if (count($violations) > 0) {
            return $this->badRequest($violations);
        }

        // Existence category
        $category = $em->getRepository(Category::class)->find((int)$payload['category_id']);
        if (!$category)
            return $this->badRequestMessage('category_id', 'Category does not exist');

        // Unicité name
        $doublon = $em->getRepository(Tool::class)->findOneBy(['name' => $payload['name']]);
        if ($doublon)
            return $this->badRequestMessage('name', 'Name must be unique');

        // On crée un nouveau tool qui sera dans la base de données
        $tool = (new Tool())
            ->setName($payload['name'])
            ->setDescription($payload['description'] ?? null)
            ->setVendor($payload['vendor'])
            ->setWebsiteUrl($payload['website_url'] ?? null)
            ->setCategory($category)
            ->setMonthlyCost((string)$payload['monthly_cost'])
            ->setOwnerDepartment($payload['owner_department'])
            ->setStatus('active')
            ->setActiveUsersCount(0)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($tool);
        $em->flush();

        return new JsonResponse($this->toolToJSON($tool), 201);
    }

    #[Route('/{id}', name: 'tools_update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $req, EntityManagerInterface $em): JsonResponse
    {
        $tool = $em->getRepository(Tool::class)->find($id);
        if (!$tool)
            return $this->notFound($id);

        $payload = json_decode($req->getContent(), true) ?? [];

        // validations
        $errors = [];
        if (array_key_exists('monthly_cost', $payload) && (!is_numeric($payload['monthly_cost']) || $payload['monthly_cost'] < 0))
            $errors['monthly_cost'] = 'Must be a positive number';
        if (array_key_exists('status', $payload) && !in_array($payload['status'], self::STATUSES))
            $errors['status'] = 'Invalid status';
        if (array_key_exists('owner_department', $payload) && !in_array($payload['owner_department'], self::DEPARTMENTS))
            $errors['owner_department'] = 'Invalid department';
        if (array_key_exists('website_url', $payload) && $payload['website_url'] !== null && !filter_var($payload['website_url'], FILTER_VALIDATE_URL))
            $errors['website_url'] = 'Must be a valid URL';
        if ($errors)
            return new JsonResponse(['error' => 'Validation failed', 'details' => $errors], 400);

        // On modifie les champs qui ont été changés puis on modifie en base de données
        if (isset($payload['name']))
            $tool->setName($payload['name']);
        if (array_key_exists('description',$payload))
            $tool->setDescription($payload['description']);
        if (isset($payload['vendor']))
            $tool->setVendor($payload['vendor']);
        if (array_key_exists('website_url',$payload))
            $tool->setWebsiteUrl($payload['website_url']);

        if (isset($payload['category_id'])) {
            $category = $em->getRepository(Category::class)->find($payload['category_id']);
            if (!$category)
                return $this->badRequestMessage('category_id', 'Category does not exist');
            $tool->setCategory($category);
        }
        if (isset($payload['monthly_cost']))
            $tool->setMonthlyCost($payload['monthly_cost']);
        if (isset($payload['owner_department']))
            $tool->setOwnerDepartment($payload['owner_department']);
        if (isset($payload['status']))
            $tool->setStatus($payload['status']);

        $tool->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse($this->toolToJSON($tool), 200);
    }

    // Transforme Tool en un tableau pour qu'il soit envoyé en JSON
    private function toolToJSON(Tool $tool): array
    {
        return [
            'id' => $tool->getId(),
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'vendor' => $tool->getVendor(),
            'website_url' => $tool->getWebsiteUrl(),
            'category' => $tool->getCategory()->getName(),
            'monthly_cost' => (float)$tool->getMonthlyCost(),
            'owner_department' => $tool->getOwnerDepartment(),
            'status' => $tool->getStatus(),
            'active_users_count' => $tool->getActiveUsersCount(),
            'created_at' => $tool->getCreatedAt()?->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $tool->getUpdatedAt()?->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function notFound(int $id): JsonResponse
    {
        return new JsonResponse(['error' => 'Tool not found', 'message' => "Tool with ID $id does not exist"], 404);
    }

    private function badRequest($violations): JsonResponse
    {
        $details = [];
        foreach ($violations as $v)
            $details[$v->getPropertyPath()] = $v->getMessage();
        return new JsonResponse(['error' => 'Validation failed', 'details' => $details], 400);
    }
    private function badRequestMessage(string $field, string $msg): JsonResponse
    {
        return new JsonResponse(['error'=>'Validation failed','details'=>[$field=>$msg]], 400);
    }
}
