<?php

namespace App\Entity;

use App\Repository\ProductModel3DRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductModel3DRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'product_model_3d')]
class ProductModel3D
{
    public const TYPE_CAN = 'can';
    public const TYPE_BOTTLE = 'bottle';
    public const TYPE_CHIP_BAG = 'chip_bag';
    public const TYPE_NOODLE_CUP = 'noodle_cup';
    public const TYPE_CANDY_BAG = 'candy_bag';
    public const TYPE_CANDY_STICK_BAG = 'candy_stick_bag';
    public const TYPE_CEREAL_BOX = 'cereal_box';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_CAN,
        self::TYPE_BOTTLE,
        self::TYPE_CHIP_BAG,
        self::TYPE_NOODLE_CUP,
        self::TYPE_CANDY_BAG,
        self::TYPE_CANDY_STICK_BAG,
        self::TYPE_CEREAL_BOX,
    ];

    public const DEFAULT_WIDTH_SCALE = 1.08;
    public const DEFAULT_HEIGHT = 4.08;
    public const DEFAULT_BODY_BULGE = 0.995;
    public const DEFAULT_SHOULDER = 1.012;
    public const DEFAULT_TOP_CUT = 0.82;
    public const DEFAULT_TOP_NECK = 0.80;
    public const DEFAULT_BOTTOM_NECK = 0.81;
    public const DEFAULT_LID_SCALE = 1.00;

    public const DEFAULT_MODEL_TYPE = self::TYPE_CAN;

    public const LIMITS = [
        'widthScale' => [0.65, 1.80],
        'height' => [1.80, 6.40],
        'bodyBulge' => [0.55, 1.30],
        'shoulder' => [0.55, 1.35],
        'topCut' => [0.00, 1.00],
        'topNeck' => [0.35, 1.08],
        'bottomNeck' => [0.35, 1.20],
        'lidScale' => [0.50, 1.40],
    ];

    public const DEFAULTS_BY_TYPE = [
        self::TYPE_CAN => [
            'widthScale' => self::DEFAULT_WIDTH_SCALE,
            'height' => self::DEFAULT_HEIGHT,
            'bodyBulge' => self::DEFAULT_BODY_BULGE,
            'shoulder' => self::DEFAULT_SHOULDER,
            'topCut' => self::DEFAULT_TOP_CUT,
            'topNeck' => self::DEFAULT_TOP_NECK,
            'bottomNeck' => self::DEFAULT_BOTTOM_NECK,
            'lidScale' => self::DEFAULT_LID_SCALE,
        ],
        self::TYPE_BOTTLE => [
            'widthScale' => 0.88,
            'height' => 5.15,
            'bodyBulge' => 0.96,
            'shoulder' => 0.88,
            'topCut' => 0.66,
            'topNeck' => 0.46,
            'bottomNeck' => 0.82,
            'lidScale' => 0.72,
        ],
        self::TYPE_CHIP_BAG => [
            'widthScale' => 1.24,
            'height' => 4.65,
            'bodyBulge' => 0.92,
            'shoulder' => 1.04,
            'topCut' => 0.86,
            'topNeck' => 0.90,
            'bottomNeck' => 0.88,
            'lidScale' => 1.00,
        ],
        self::TYPE_NOODLE_CUP => [
            'widthScale' => 1.10,
            'height' => 2.95,
            'bodyBulge' => 0.88,
            'shoulder' => 1.22,
            'topCut' => 0.78,
            'topNeck' => 1.04,
            'bottomNeck' => 0.70,
            'lidScale' => 1.05,
        ],
        self::TYPE_CANDY_BAG => [
            'widthScale' => 1.12,
            'height' => 4.05,
            'bodyBulge' => 0.78,
            'shoulder' => 1.00,
            'topCut' => 0.84,
            'topNeck' => 0.92,
            'bottomNeck' => 0.88,
            'lidScale' => 0.90,
        ],
        self::TYPE_CANDY_STICK_BAG => [
            'widthScale' => 0.78,
            'height' => 4.55,
            'bodyBulge' => 0.62,
            'shoulder' => 0.96,
            'topCut' => 0.88,
            'topNeck' => 0.88,
            'bottomNeck' => 0.84,
            'lidScale' => 0.78,
        ],
        self::TYPE_CEREAL_BOX => [
            'widthScale' => 1.18,
            'height' => 4.75,
            'bodyBulge' => 0.82,
            'shoulder' => 1.00,
            'topCut' => 0.92,
            'topNeck' => 1.00,
            'bottomNeck' => 1.00,
            'lidScale' => 0.92,
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Product $product = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(length: 40, options: ['default' => self::DEFAULT_MODEL_TYPE])]
    #[Assert\Choice(choices: self::TYPES)]
    private string $modelType = self::DEFAULT_MODEL_TYPE;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $texturePath = null;

    #[ORM\Column]
    private float $widthScale = self::DEFAULT_WIDTH_SCALE;

    #[ORM\Column]
    private float $height = self::DEFAULT_HEIGHT;

    #[ORM\Column]
    private float $bodyBulge = self::DEFAULT_BODY_BULGE;

    #[ORM\Column]
    private float $shoulder = self::DEFAULT_SHOULDER;

    #[ORM\Column]
    private float $topCut = self::DEFAULT_TOP_CUT;

    #[ORM\Column]
    private float $topNeck = self::DEFAULT_TOP_NECK;

    #[ORM\Column]
    private float $bottomNeck = self::DEFAULT_BOTTOM_NECK;

    #[ORM\Column]
    private float $lidScale = self::DEFAULT_LID_SCALE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

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

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function setModelType(string $modelType): self
    {
        $modelType = trim($modelType);

        if (!in_array($modelType, self::TYPES, true)) {
            throw new \InvalidArgumentException('admin.model_3d.error.invalid_model_type');
        }

        $this->modelType = $modelType;

        return $this;
    }

    public function getTexturePath(): ?string
    {
        return $this->texturePath;
    }

    public function setTexturePath(?string $texturePath): self
    {
        $texturePath = null === $texturePath ? null : trim($texturePath);
        $this->texturePath = '' === $texturePath ? null : $texturePath;

        return $this;
    }

    public function getTextureFilename(): ?string
    {
        if (null === $this->texturePath || '' === $this->texturePath) {
            return null;
        }

        return basename($this->texturePath);
    }

    public function getWidthScale(): float
    {
        return $this->widthScale;
    }

    public function setWidthScale(float $widthScale): self
    {
        $this->widthScale = $widthScale;

        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getBodyBulge(): float
    {
        return $this->bodyBulge;
    }

    public function setBodyBulge(float $bodyBulge): self
    {
        $this->bodyBulge = $bodyBulge;

        return $this;
    }

    public function getShoulder(): float
    {
        return $this->shoulder;
    }

    public function setShoulder(float $shoulder): self
    {
        $this->shoulder = $shoulder;

        return $this;
    }

    public function getTopCut(): float
    {
        return $this->topCut;
    }

    public function setTopCut(float $topCut): self
    {
        $this->topCut = $topCut;

        return $this;
    }

    public function getTopNeck(): float
    {
        return $this->topNeck;
    }

    public function setTopNeck(float $topNeck): self
    {
        $this->topNeck = $topNeck;

        return $this;
    }

    public function getBottomNeck(): float
    {
        return $this->bottomNeck;
    }

    public function setBottomNeck(float $bottomNeck): self
    {
        $this->bottomNeck = $bottomNeck;

        return $this;
    }

    public function getLidScale(): float
    {
        return $this->lidScale;
    }

    public function setLidScale(float $lidScale): self
    {
        $this->lidScale = $lidScale;

        return $this;
    }

    /**
     * @return array{
     *     widthScale: float,
     *     height: float,
     *     bodyBulge: float,
     *     shoulder: float,
     *     topCut: float,
     *     topNeck: float,
     *     bottomNeck: float,
     *     lidScale: float
     * }
     */
    public function toShapeArray(): array
    {
        return [
            'widthScale' => $this->widthScale,
            'height' => $this->height,
            'bodyBulge' => $this->bodyBulge,
            'shoulder' => $this->shoulder,
            'topCut' => $this->topCut,
            'topNeck' => $this->topNeck,
            'bottomNeck' => $this->bottomNeck,
            'lidScale' => $this->lidScale,
        ];
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
}
