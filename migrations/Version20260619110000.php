<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move imported product image paths from ultrapop.com to local AssetMapper paths.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE product_image
             SET path = CONCAT('img/products/', SUBSTRING_INDEX(path, '/', -1))
             WHERE path LIKE 'https://ultrapop.com/img/p/%'",
        );
    }

    public function down(Schema $schema): void
    {
        $paths = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT path FROM product_image WHERE path LIKE 'img/products/%'",
        );

        foreach ($paths as $path) {
            $filename = basename((string) $path);

            if ('fr-default-large_default.jpg' === $filename) {
                $remotePath = 'https://ultrapop.com/img/p/fr-default-large_default.jpg';
            } elseif (preg_match('/^(\d+)-/', $filename, $matches)) {
                $remotePath = sprintf(
                    'https://ultrapop.com/img/p/%s/%s',
                    implode('/', str_split($matches[1])),
                    $filename,
                );
            } else {
                continue;
            }

            $this->addSql(
                'UPDATE product_image SET path = :remotePath WHERE path = :localPath',
                [
                    'remotePath' => $remotePath,
                    'localPath' => $path,
                ],
            );
        }
    }
}
