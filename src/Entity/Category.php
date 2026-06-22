<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Category
{
    public const MAX_DEPTH = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection $products;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null === $description ? null : trim($description);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        if ($this->parent === $parent) {
            return $this;
        }

        if ($parent === $this || $parent?->isDescendantOf($this)) {
            throw new \InvalidArgumentException('admin.category.error.invalid_parent');
        }

        $previousParent = $this->parent;
        $this->parent = $parent;
        $previousParent?->children->removeElement($this);

        if ($parent instanceof self && !$parent->children->contains($this)) {
            $parent->children->add($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        $child->setParent($this);

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child) && $child->parent === $this) {
            $child->parent = null;
        }

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        $visited = [];

        while ($current instanceof self) {
            $objectId = spl_object_id($current);

            if (isset($visited[$objectId])) {
                break;
            }

            $visited[$objectId] = true;
            ++$depth;
            $current = $current->parent;
        }

        return $depth;
    }

    public function isDescendantOf(self $ancestor): bool
    {
        $current = $this->parent;
        $visited = [];

        while ($current instanceof self) {
            if ($current === $ancestor) {
                return true;
            }

            $objectId = spl_object_id($current);

            if (isset($visited[$objectId])) {
                return false;
            }

            $visited[$objectId] = true;
            $current = $current->parent;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getPathNames(): array
    {
        $path = [$this->name];
        $current = $this->parent;
        $visited = [spl_object_id($this) => true];

        while ($current instanceof self) {
            $objectId = spl_object_id($current);

            if (isset($visited[$objectId])) {
                break;
            }

            $visited[$objectId] = true;
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return $path;
    }

    public function getPathLabel(): string
    {
        return implode(' / ', $this->getPathNames());
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);

            if ($product->getCategory() !== $this) {
                $product->setCategory($this);
            }
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);

        return $this;
    }

    /**
     * @return list<Product>
     */
    public function getProductsRecursive(): array
    {
        $products = [];
        $visitedCategories = [];
        $this->collectProducts($products, $visitedCategories);

        return array_values($products);
    }

    public function getProductCountRecursive(): int
    {
        return \count($this->getProductsRecursive());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateHierarchy(ExecutionContextInterface $context): void
    {
        if ($this->parent === $this || $this->parent?->isDescendantOf($this)) {
            $context
                ->buildViolation('admin.category.error.invalid_parent')
                ->atPath('parent')
                ->addViolation();
        }

        if ($this->getDepth() > self::MAX_DEPTH) {
            $context
                ->buildViolation('admin.category.error.max_depth')
                ->atPath('parent')
                ->addViolation();
        }

        if ($this->children->count() > 0 && $this->products->count() > 0) {
            $context
                ->buildViolation('admin.category.error.parent_has_products')
                ->atPath('parent')
                ->addViolation();
        }

        if ($this->parent instanceof self && $this->parent->getProducts()->count() > 0) {
            $context
                ->buildViolation('admin.category.error.invalid_parent_products')
                ->atPath('parent')
                ->addViolation();
        }
    }

    /**
     * @param array<int, Product> $products
     * @param array<int, true>    $visitedCategories
     */
    private function collectProducts(array &$products, array &$visitedCategories): void
    {
        $categoryObjectId = spl_object_id($this);

        if (isset($visitedCategories[$categoryObjectId])) {
            return;
        }

        $visitedCategories[$categoryObjectId] = true;

        foreach ($this->products as $product) {
            $products[spl_object_id($product)] = $product;
        }

        foreach ($this->children as $child) {
            $child->collectProducts($products, $visitedCategories);
        }
    }
}
