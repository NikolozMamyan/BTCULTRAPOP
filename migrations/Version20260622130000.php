<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair category aliases, parents and positions on imported MariaDB catalogs.';
    }

    public function up(Schema $schema): void
    {
        $this->insertCategory('Tout', 'Racine du catalogue.', 0);
        $this->insertCategory('Boissons', 'Boissons et thés prêts à boire.', 10);
        $this->insertCategory('Épicerie salée', 'Produits salés et repas instantanés.', 20);
        $this->insertCategory('Épicerie sucrée', 'Bonbons et céréales.', 30);
        $this->insertCategory('Coffrets', 'Coffrets et assortiments.', 40);

        $this->normalizeAlias('Boissons aromatisées', 'Jus');
        $this->normalizeAlias('BOUTEILLE 33CL', 'Jus');
        $this->normalizeAlias('Bobbasanss', 'Bobbasan - Bubble Tea');
        $this->normalizeAlias('BUBBLE TEA', 'Bobbasan - Bubble Tea');
        $this->normalizeAlias('Soda', 'Sodas');
        $this->normalizeAlias('FAT CANETTE', 'Sodas');
        $this->normalizeAlias('Chipsan - Chips de pommes de terre', 'Chipsan - Chips de Pommes de terre');
        $this->normalizeAlias('Negisan - Nouilles instantanées', 'Négisan - Nouilles instantanées');
        $this->normalizeAlias('Gumisan - Sachets de bonbons', 'Gumisan - Bonbons');
        $this->normalizeAlias('Yokosan - Boites de céréales', 'Yokosan - Céréales');

        $this->attachCategories(['Boissons', 'Épicerie salée', 'Épicerie sucrée', 'Coffrets'], 'Tout');
        $this->attachCategories(['Jus', 'Bobbasan - Bubble Tea', 'Sodas', 'Ultra Ice Tea'], 'Boissons');
        $this->attachCategories(
            ['Chipsan - Chips de Pommes de terre', 'Komesan - Chips de riz', 'Négisan - Nouilles instantanées'],
            'Épicerie salée',
        );
        $this->attachCategories(['Gumisan - Bonbons', 'Yokosan - Céréales'], 'Épicerie sucrée');
        $this->attachCategories(['Box bundle'], 'Coffrets');

        $this->setCategoryPositions([
            'Tout' => 0,
            'Boissons' => 10,
            'Jus' => 10,
            'Bobbasan - Bubble Tea' => 20,
            'Sodas' => 30,
            'Ultra Ice Tea' => 40,
            'Épicerie salée' => 20,
            'Chipsan - Chips de Pommes de terre' => 10,
            'Komesan - Chips de riz' => 20,
            'Négisan - Nouilles instantanées' => 30,
            'Épicerie sucrée' => 30,
            'Gumisan - Bonbons' => 10,
            'Yokosan - Céréales' => 20,
            'Coffrets' => 40,
            'Box bundle' => 10,
        ]);

        $this->addSql("UPDATE category SET parent_id = NULL WHERE name = 'Tout'");
        $this->addSql(
            "UPDATE category
             SET active = 1
             WHERE name IN (
                 'Tout',
                 'Boissons',
                 'Jus',
                 'Bobbasan - Bubble Tea',
                 'Sodas',
                 'Ultra Ice Tea',
                 'Épicerie salée',
                 'Chipsan - Chips de Pommes de terre',
                 'Komesan - Chips de riz',
                 'Négisan - Nouilles instantanées',
                 'Épicerie sucrée',
                 'Gumisan - Bonbons',
                 'Yokosan - Céréales',
                 'Coffrets',
                 'Box bundle'
             )",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'The original imported category aliases cannot be reconstructed reliably.',
        );
    }

    private function insertCategory(string $name, string $description, int $position): void
    {
        $this->addSql(
            'INSERT INTO category (name, description, active, position, created_at, updated_at)
             SELECT :name, :description, 1, :position, NOW(), NOW()
             WHERE NOT EXISTS (SELECT 1 FROM category WHERE name = :name)',
            ['name' => $name, 'description' => $description, 'position' => $position],
        );
    }

    private function normalizeAlias(string $alias, string $target): void
    {
        $this->addSql(
            'UPDATE product product
             INNER JOIN category alias_category ON alias_category.name = :alias
             INNER JOIN category target_category
                 ON target_category.name = :target
                 AND target_category.id <> alias_category.id
             SET product.category_id = target_category.id
             WHERE product.category_id = alias_category.id',
            ['alias' => $alias, 'target' => $target],
        );
        $this->addSql(
            'DELETE alias_category
             FROM category alias_category
             INNER JOIN category target_category
                 ON target_category.name = :target
                 AND target_category.id <> alias_category.id
             WHERE alias_category.name = :alias',
            ['alias' => $alias, 'target' => $target],
        );
        $this->addSql(
            'UPDATE category SET name = :target WHERE name = :alias',
            ['alias' => $alias, 'target' => $target],
        );
    }

    /**
     * @param list<string> $children
     */
    private function attachCategories(array $children, string $parent): void
    {
        $this->addSql(
            'UPDATE category child
             INNER JOIN category parent ON parent.name = :parent
             SET child.parent_id = parent.id
             WHERE child.name IN (:children)',
            ['parent' => $parent, 'children' => $children],
            ['children' => ArrayParameterType::STRING],
        );
    }

    /**
     * @param array<string, int> $positions
     */
    private function setCategoryPositions(array $positions): void
    {
        foreach ($positions as $name => $position) {
            $this->addSql(
                'UPDATE category SET position = :position WHERE name = :name',
                ['name' => $name, 'position' => $position],
            );
        }
    }
}
