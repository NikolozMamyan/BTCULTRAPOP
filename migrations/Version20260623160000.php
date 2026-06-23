<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distribute between zero and seven varied preproduction reviews across active products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM product_review WHERE editorial = 1');

        $this->addSql(<<<'SQL'
            INSERT INTO product_review (
                product_id,
                author_name,
                title,
                content,
                rating,
                editorial,
                published,
                created_at
            )
            SELECT
                product.id,
                CASE MOD(product.id + review_slot.slot, 24)
                    WHEN 0 THEN 'Léa M.'
                    WHEN 1 THEN 'Nicolas R.'
                    WHEN 2 THEN 'Sarah B.'
                    WHEN 3 THEN 'Lucas T.'
                    WHEN 4 THEN 'Camille D.'
                    WHEN 5 THEN 'Thomas L.'
                    WHEN 6 THEN 'Manon G.'
                    WHEN 7 THEN 'Hugo P.'
                    WHEN 8 THEN 'Chloé V.'
                    WHEN 9 THEN 'Maxime C.'
                    WHEN 10 THEN 'Inès F.'
                    WHEN 11 THEN 'Alexandre N.'
                    WHEN 12 THEN 'Mathilde C.'
                    WHEN 13 THEN 'Julien V.'
                    WHEN 14 THEN 'Emma L.'
                    WHEN 15 THEN 'Antoine G.'
                    WHEN 16 THEN 'Clara P.'
                    WHEN 17 THEN 'Romain B.'
                    WHEN 18 THEN 'Julie N.'
                    WHEN 19 THEN 'Enzo M.'
                    WHEN 20 THEN 'Laura T.'
                    WHEN 21 THEN 'Nathan D.'
                    WHEN 22 THEN 'Anaïs R.'
                    ELSE 'Gabriel F.'
                END,
                CASE review_slot.slot
                    WHEN 1 THEN 'Très bonne découverte'
                    WHEN 2 THEN 'Un visuel vraiment réussi'
                    WHEN 3 THEN 'Une agréable surprise'
                    WHEN 4 THEN 'Sympa pour les fans'
                    WHEN 5 THEN 'Bon produit'
                    WHEN 6 THEN 'Une référence originale'
                    ELSE 'Conforme à mes attentes'
                END,
                CASE review_slot.slot
                    WHEN 1 THEN CONCAT('Le goût est agréable et le packaging de ', product.name, ' rend vraiment bien. Une bonne découverte.')
                    WHEN 2 THEN CONCAT('Le design lié à ', product_license.name, ' est soigné. Le produit est aussi sympa à offrir qu’à garder pour soi.')
                    WHEN 3 THEN 'Une saveur bien équilibrée et un format pratique. Je ne connaissais pas cette gamme et je suis agréablement surpris.'
                    WHEN 4 THEN CONCAT('Un produit original pour les fans de ', product_license.name, '. Le mélange entre gourmandise et manga fonctionne bien.')
                    WHEN 5 THEN 'Bon produit dans l’ensemble, avec un visuel fidèle à la licence et une saveur agréable.'
                    WHEN 6 THEN 'Le packaging attire tout de suite l’œil et le produit tient ses promesses. Une référence qui change des produits habituels.'
                    ELSE 'Produit conforme à la présentation, bien emballé et plaisant à découvrir. Je recommande pour compléter une sélection pop culture.'
                END,
                CASE MOD(product.id + review_slot.slot, 8)
                    WHEN 0 THEN 4
                    WHEN 1 THEN 4
                    ELSE 5
                END,
                1,
                1,
                TIMESTAMPADD(DAY, -(review_slot.slot * 4 + MOD(product.id, 18)), CURRENT_TIMESTAMP)
            FROM product
            INNER JOIN product_license ON product_license.id = product.license_id
            INNER JOIN (
                SELECT 1 AS slot
                UNION ALL SELECT 2
                UNION ALL SELECT 3
                UNION ALL SELECT 4
                UNION ALL SELECT 5
                UNION ALL SELECT 6
                UNION ALL SELECT 7
            ) review_slot ON review_slot.slot <= MOD(product.id + 3, 8)
            WHERE product.active = 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM product_review WHERE editorial = 1');

        $this->addSql(<<<'SQL'
            INSERT INTO product_review (product_id, author_name, title, content, rating, editorial, published, created_at)
            SELECT
                product.id,
                CASE MOD(product.id, 12)
                    WHEN 0 THEN 'Léa M.'
                    WHEN 1 THEN 'Nicolas R.'
                    WHEN 2 THEN 'Sarah B.'
                    WHEN 3 THEN 'Lucas T.'
                    WHEN 4 THEN 'Camille D.'
                    WHEN 5 THEN 'Thomas L.'
                    WHEN 6 THEN 'Manon G.'
                    WHEN 7 THEN 'Hugo P.'
                    WHEN 8 THEN 'Chloé V.'
                    WHEN 9 THEN 'Maxime C.'
                    WHEN 10 THEN 'Inès F.'
                    ELSE 'Alexandre N.'
                END,
                'Un univers bien mis en valeur',
                CONCAT(
                    'Le visuel de ',
                    product.name,
                    ' met immédiatement son univers en valeur. Une référence originale qui associe gourmandise et pop culture avec une identité forte.'
                ),
                5,
                1,
                1,
                CURRENT_TIMESTAMP - INTERVAL 3 DAY
            FROM product
            WHERE product.active = 1
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO product_review (product_id, author_name, title, content, rating, editorial, published, created_at)
            SELECT
                product.id,
                CASE MOD(product.id, 12)
                    WHEN 0 THEN 'Mathilde C.'
                    WHEN 1 THEN 'Julien V.'
                    WHEN 2 THEN 'Emma L.'
                    WHEN 3 THEN 'Antoine G.'
                    WHEN 4 THEN 'Clara P.'
                    WHEN 5 THEN 'Romain B.'
                    WHEN 6 THEN 'Julie N.'
                    WHEN 7 THEN 'Enzo M.'
                    WHEN 8 THEN 'Laura T.'
                    WHEN 9 THEN 'Nathan D.'
                    WHEN 10 THEN 'Anaïs R.'
                    ELSE 'Gabriel F.'
                END,
                'Une découverte pop et gourmande',
                CONCAT(
                    'Une découverte agréable, avec un format facile à partager et une licence ',
                    product_license.name,
                    ' bien reconnaissable. Le produit trouve naturellement sa place dans une sélection dédiée aux fans.'
                ),
                4,
                1,
                1,
                CURRENT_TIMESTAMP - INTERVAL 11 DAY
            FROM product
            INNER JOIN product_license ON product_license.id = product.license_id
            WHERE product.active = 1
        SQL);
    }
}
