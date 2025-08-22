<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $name;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 7)]
    private string $color_hex = '#6366f1';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function getId(): ?int {
        return $this->id;
    }
    public function getName(): string {
        return $this->name;
    }
    public function setName(string $name): Category {
        $this->name = $name;
        return $this;
    }
    public function getDescription(): ?string {
        return $this->description;
    }
    public function setDescription(?string $description): Category {
        $this->description = $description;
        return $this;
    }

    public function getColorHex(): string {
        return $this->color_hex;
    }
    public function setColorHex(string $color_hex): Category {
        $this->color_hex = $color_hex;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface {
        return $this->created_at;
    }
    public function setCreatedAt(\DateTimeInterface $created_at): Category {
        $this->created_at = $created_at;
        return $this;
    }

}
