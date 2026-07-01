<?php

namespace App\Tests\Entity;

use App\Entity\ProductModel3D;
use PHPUnit\Framework\TestCase;

final class ProductModel3DTest extends TestCase
{
    public function testItExposesTheConfiguredShapeForTheStorefront(): void
    {
        $model = (new ProductModel3D())
            ->setModelType(ProductModel3D::TYPE_BOTTLE)
            ->setTexturePath('img/3dproduct/jus-test.png')
            ->setWidthScale(1.08)
            ->setHeight(4.08)
            ->setBodyBulge(0.995)
            ->setShoulder(1.012)
            ->setTopCut(0.82)
            ->setTopNeck(0.80)
            ->setBottomNeck(0.81)
            ->setLidScale(1.00);

        self::assertSame([
            'widthScale' => 1.08,
            'height' => 4.08,
            'bodyBulge' => 0.995,
            'shoulder' => 1.012,
            'topCut' => 0.82,
            'topNeck' => 0.80,
            'bottomNeck' => 0.81,
            'lidScale' => 1.00,
        ], $model->toShapeArray());
        self::assertSame('img/3dproduct/jus-test.png', $model->getTexturePath());
        self::assertSame('jus-test.png', $model->getTextureFilename());
        self::assertSame(ProductModel3D::TYPE_BOTTLE, $model->getModelType());
    }
}
