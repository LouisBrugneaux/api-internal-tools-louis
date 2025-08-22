<?php

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ToolRepository::class)]
#[ORM\Table(name: 'tools')]
class Tool
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $vendor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $website_url = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private Category $category;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $monthly_cost;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $active_users_count = 0;

    #[ORM\Column(type: 'string')]
    private string $owner_department;

    #[ORM\Column(type: 'string', options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    // getters/setters... (garde minimal, utile dans le contrôleur)
    public function getId(): ?int {
        return $this->id;
    }
    public function getName(): string {
        return $this->name;
    }
    public function setName(string $name): Tool {
        $this->name = $name;
        return $this;
    }
    public function getDescription(): ?string {
        return $this->description;
    }
    public function setDescription(?string $description): Tool {
        $this->description = $description;
        return $this;
    }
    public function getVendor(): ?string {
        return $this->vendor;
    }
    public function setVendor(?string $vendor): Tool {
        $this->vendor = $vendor;
        return $this;
    }
    public function getWebsiteUrl(): ?string {
        return $this->website_url;
    }
    public function setWebsiteUrl(?string $website_url): Tool {
        $this->website_url = $website_url;
        return $this;
    }
    public function getCategory(): Category {
        return $this->category;
    }
    public function setCategory(Category $category): Tool {
        $this->category = $category;
        return $this;
    }
    public function getMonthlyCost(): string {
        return $this->monthly_cost;
    }
    public function setMonthlyCost(string $monthly_cost): Tool {
        $this->monthly_cost = $monthly_cost;
        return $this;
    }
    public function getActiveUsersCount(): int {
        return $this->active_users_count;
    }
    public function setActiveUsersCount(int $active_users_count): Tool {
        $this->active_users_count = $active_users_count;
        return $this;
    }
    public function getOwnerDepartment(): string {
        return $this->owner_department;
    }
    public function setOwnerDepartment(string $owner_department): Tool {
        $this->owner_department = $owner_department;
        return $this;
    }
    public function getStatus(): string {
        return $this->status;
    }
    public function setStatus(string $status): Tool {
        $this->status = $status;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeInterface {
        return $this->created_at;
    }
    public function setCreatedAt(?\DateTimeInterface $created_at): Tool {
        $this->created_at = $created_at;
        return $this;
    }
    public function getUpdatedAt(): ?\DateTimeInterface {
        return $this->updated_at;
    }
    public function setUpdatedAt(?\DateTimeInterface $updated_at): Tool {
        $this->updated_at = $updated_at;
        return $this;
    }
}
