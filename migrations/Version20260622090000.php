<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622090000 extends AbstractMigration
{
    /**
     * Only products represented unambiguously in the supplied catalog are renamed.
     *
     * @var array<string, string>
     */
    private const PRODUCT_NAMES = [
        '30623' => 'ULTRAPOP - Demon Slayer - Nezuko - Litchi 33cl',
        '30654' => 'ULTRAPOP - Demon Slayer - Tanjiro - Litchi 33cl',
        '30562' => 'ULTRAPOP - Demon Slayer - Inosuke - Litchi 33cl',
        '30593' => 'ULTRAPOP - Demon Slayer - Zenitsu - Litchi 33cl',
        '30823' => 'ULTRAPOP - Dragon Ball Z - Goku - Fraise 33cl',
        '30793' => 'ULTRAPOP - Dragon Ball Z - Vegeta - Fraise 33cl',
        '30037' => 'ULTRAPOP - One Piece - Luffy - Cerise 33cl',
        '30006' => 'ULTRAPOP - One Piece - Zoro - Cerise 33cl',
        '30946' => 'ULTRAPOP - Naruto - Naruto - Tropical 33cl',
        '30915' => 'ULTRAPOP - Naruto - Sasuke - Tropical 33cl',
        '30854' => 'ULTRAPOP - League of Legend - Ahri - Tropical 33cl',
        '30449' => 'ULTRAPOP - League of Legend - Yasuo - Tropical 33cl',
        '39389' => 'ULTRAPOP - Dragon Ball Z - Goku - Poire & Melon 32cl',
        '39419' => 'ULTRAPOP - Naruto - Naruto - Fruit de la passion & Litchi 32cl',
        '39440' => 'ULTRAPOP - One Piece - Luffy - Fraise & Pêche 32cl',
        '38870' => 'ULTRAPOP - Dragon Ball Super - Goku - Fraise & Banane 33cl',
        '38788' => 'ULTRAPOP - Naruto - Naruto - Orange 33cl',
        '38849' => 'ULTRAPOP - One Piece - Citron & Fraise 33cl',
        '28965' => 'ULTRAPOP - Dragon Ball Z - Goku - Ice tea Pêche 33cl',
        '28989' => 'ULTRAPOP - Dragon Ball Z - Vegeta - Ice tea Pêche 33cl',
        '28927' => 'ULTRAPOP - One Piece - Luffy - Ice tea Pêche 33cl',
        '28903' => 'ULTRAPOP - One Piece - Zoro - Ice tea Pêche 33cl',
        '28569' => 'ULTRAPOP - Naruto - Naruto - Ice tea Pêche 33cl',
        '28545' => 'ULTRAPOP - Naruto - Sasuke - Ice tea Pêche 33cl',
        '72067' => 'ULTRAPOP - Dragon Ball Z - Goku vs Freezer - Oignons Caramélisés 110g',
        '70128' => 'ULTRAPOP - One Piece - Luffy vs Lucci - Poulet & Citron 110g',
        '71098' => 'ULTRAPOP - Naruto - Naruto vs Sasuke - Pizza 110g',
        '73746' => 'ULTRAPOP - Demon Slayer - Rui vs Tanjiro - Teriyaki 110g',
        '41665' => 'ULTRAPOP - Naruto - Kakashi - Crème et oignon 60g',
        '41689' => 'ULTRAPOP - Naruto - Naruto - Fromage 60g',
        '42634' => 'ULTRAPOP - Dragon Ball Z - Goku - Pizza 60g',
        '42603' => 'ULTRAPOP - Dragon Ball Z - Vegeta - Piment 60g',
        '40702' => 'ULTRAPOP - One Piece - Zoro - Ketchup 60g',
        '40726' => 'ULTRAPOP - One Piece - Luffy - Barbecue 60g',
        '43678' => 'ULTRAPOP - Demon Slayer - Moutarde et Miel 60g',
        '61863' => 'ULTRAPOP - Naruto - Naruto - Poulet Teriyaki 65g',
        '66145' => 'ULTRAPOP -  Dragon Ball Super - Curry 65g',
        '60144' => 'ULTRAPOP - One Piece - Luffi - Boeuf épicé 65g',
        '50320' => 'ULTRAPOP - One Piece - Luffy - Bonbons gélifiés Raisin 180g',
        '51269' => 'ULTRAPOP - Naruto - Naruto - Bonbons gélifiés Cerise & Pomme 180g',
        '52559' => 'ULTRAPOP - Dragon Ball Super - Goku - Bonbons fils Tropical 75g',
        '50566' => 'ULTRAPOP - One Piece - Luffy - Bonbons fils Fraise 75g',
        '51163' => 'ULTRAPOP - Naruto - Naruto - Bonbons rubans Fraise 75gr',
        '80112' => 'ULTRAPOP - Dragon Ball Super - Miel 350g',
        '80022' => 'ULTRAPOP - Naruto - Cookies crème 350g',
        '80053' => 'ULTRAPOP - One Piece - Chocolat 350g',
    ];

    /**
     * @var array<string, string>
     */
    private const ORIGINAL_PRODUCT_NAMES = [
        '30623' => 'ULTRAPOP - Nezuko - Demon Slayer - Litchi 33cL',
        '30654' => 'ULTRAPOP - Tanjiro - Demon Slayer - Litchi 33cL',
        '30562' => 'ULTRAPOP - Inosuke - Demon Slayer - Litchi 33cL',
        '30593' => 'ULTRAPOP - Zenitsu - Demon Slayer - Litchi 33cL',
        '30823' => 'ULTRAPOP - Goku Chibi - Dragon Ball Z - Fraise 33cL',
        '30793' => 'ULTRAPOP - Vegeta Chibi - Dragon Ball Z - Fraise 33cL',
        '30037' => 'ULTRAPOP - Luffy Chibi - One Piece - Cerise 33cL',
        '30006' => 'ULTRAPOP - Zoro Chibi - One Piece - Cerise 33cL',
        '30946' => 'ULTRAPOP - Naruto Chibi - Naruto - Tropical 33cL',
        '30915' => 'ULTRAPOP - Sasuke Chibi - Naruto - Tropical 33cL',
        '30854' => 'ULTRAPOP - PET 33 CL DRAGON FRUIT - LOL - AHRI',
        '30449' => 'ULTRAPOP - PET 33CL DRAGON FRUIT - LOL - YASUO',
        '39389' => 'BOBBASAN - Goku - Dragon Ball Super - Bubble Tea Poire & Melon 32cL',
        '39419' => 'BOBBASAN - Naruto - Naruto - Bubble Tea Fruit de la Passion & Litchi 32cL',
        '39440' => 'BOBBASAN - Luffy - One Piece - Bubble Tea Fraise & Pêche 32cL',
        '38870' => 'ULTRAPOP - Goku - Dragon Ball Super - Soda Fraise Banane 33cL',
        '38788' => 'ULTRAPOP - Naruto - Naruto - Soda Orange 33cL',
        '38849' => 'ULTRAPOP - Luffy - One Piece - Soda Citron Fraise 33cL',
        '28965' => 'ULTRA ICE TEA - Goku - Dragon Ball Z - Ice Tea Pêche 33cL',
        '28989' => 'ULTRA ICE TEA - Vegeta - Dragon Ball Z - Ice Tea Pêche 33cL',
        '28927' => 'ULTRA ICE TEA - Luffy - One Piece - Ice Tea Pêche 33cL',
        '28903' => 'ULTRA ICE TEA - Zoro - One Piece - Ice Tea Pêche 33cL',
        '28569' => 'ULTRA ICE TEA - Naruto - Naruto - Ice Tea Pêche 33cL',
        '28545' => 'ULTRA ICE TEA - Sasuke - Naruto - Ice Tea Pêche 33cL',
        '72067' => 'CHIPSAN - Goku VS Freezer - Dragon Ball Z - Chips Oignons Caramélisés 110g',
        '70128' => 'CHIPSAN - Luffy VS Lucci - One Piece - Chips Poulet & Citron 110g',
        '71098' => 'CHIPSAN - Naruto VS Sasuke - Naruto - Chips Pizza 110g',
        '73746' => 'CHIPSAN - Rui VS Tanjiro - Demon Slayer - Chips Teriyaki 110g',
        '41665' => 'KOMESAN - Kakashi - Naruto - Chips de riz Cream & Onion 60g',
        '41689' => 'KOMESAN - Naruto - Naruto - Chips de riz Fromage 60g',
        '42634' => 'KOMESAN - Goku - Dragon Ball Super - Pizza 60g',
        '42603' => 'KOMESAN - Vegeta - Dragon Ball Super - Piment 60g',
        '40702' => 'KOMESAN - Zoro - One Piece - Chips de riz Ketchup 60g',
        '40726' => 'KOMESAN - Luffy - One Piece - Chips de riz Barbecue 60g',
        '43678' => 'KOMESAN - Demon Slayer - Chips de riz Moutarde & Miel 60g',
        '61863' => 'NEGISAN - Naruto, Sasuke - Naruto - Nouilles instantanées Poulet Teriyaki 65g',
        '66145' => 'NEW 2026 - ULTRA POP - NEGISAN - CUP NOUILLES INSTANTANES CURRY - DBS - GOKU',
        '60144' => 'NEGISAN - Robin, Franky, Brook - One Piece - Nouilles instantanées Bœuf épicé 65g',
        '50320' => 'GUMISAN - Luffy - One Piece - Bonbons gélifiés Raisin 180g',
        '51269' => 'GUMISAN - Naruto - Naruto - Bonbons gélifiés Cerise et Pomme 180g',
        '52559' => 'GUMISAN - Goku - Dragon Ball Super - Bonbons fils Tropical 75g',
        '50566' => 'GUMISAN - Luffy - One Piece - Bonbons fils Fraise 75g',
        '51163' => 'GUMISAN - Naruto - Naruto - Bonbons rubans Fraise 75g',
        '80112' => 'YOKOSAN - Dragon Ball Super - Céréales Miel 350g',
        '80022' => 'YOKOSAN - Naruto Shippuden - Céréales Cookies Cream 350g',
        '80053' => 'YOKOSAN - One Piece - Céréales Chocolat 350g',
    ];

    public function getDescription(): string
    {
        return 'Create the three-level product category hierarchy and normalize the supplied product names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD parent_id INT DEFAULT NULL, ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX IDX_64C19C1727ACA70 ON category (parent_id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');

        $this->insertCategory('Tout', 'Racine du catalogue.', 0);
        $this->insertCategory('Boissons', 'Boissons et thés prêts à boire.', 10);
        $this->insertCategory('Épicerie salée', 'Produits salés et repas instantanés.', 20);
        $this->insertCategory('Épicerie sucrée', 'Bonbons et céréales.', 30);
        $this->insertCategory('Coffrets', 'Coffrets et assortiments.', 40);

        $this->moveProductsToCategory(
            ['32112', '32143', '32174', '32204', '32235', '32266', '32297', '33411', '33442', '33473', '33503', '33534', '18008', '18077'],
            'Ultra Ice Tea',
        );
        $this->moveProductsToCategory(['41705', '41729', '43729', '42603', '42634'], 'Komesan - Chips de riz');

        $this->addSql("DELETE FROM category WHERE name IN ('Ultra Ice Tea - Thé noir Aromatisé', 'Dragon Ball Super', 'Tous les produits')");

        $this->renameCategory('Boissons aromatisées', 'Jus');
        $this->renameCategory('Bobbasanss', 'Bobbasan - Bubble Tea');
        $this->renameCategory('Soda', 'Sodas');
        $this->renameCategory('Chipsan - Chips de pommes de terre', 'Chipsan - Chips de Pommes de terre');
        $this->renameCategory('Negisan - Nouilles instantanées', 'Négisan - Nouilles instantanées');
        $this->renameCategory('Gumisan - Sachets de bonbons', 'Gumisan - Bonbons');
        $this->renameCategory('Yokosan - Boites de céréales', 'Yokosan - Céréales');

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

        $this->addSql('UPDATE category SET active = 1');

        foreach (self::PRODUCT_NAMES as $reference => $name) {
            $this->renameProduct($reference, $name);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::ORIGINAL_PRODUCT_NAMES as $reference => $name) {
            $this->renameProduct($reference, $name);
        }

        $this->insertCategory('Ultra Ice Tea - Thé noir Aromatisé', null, 0);
        $this->insertCategory('Dragon Ball Super', null, 0);
        $this->insertCategory('Tous les produits', null, 0);

        $this->moveProductsToCategory(
            ['32112', '32143', '32174', '32204', '32235', '32266', '32297', '33411', '33442', '33473', '33503', '33534'],
            'Ultra Ice Tea - Thé noir Aromatisé',
        );
        $this->moveProductsToCategory(['18008', '18077'], 'Dragon Ball Super');
        $this->moveProductsToCategory(['41705', '41729', '43729'], 'Chipsan - Chips de Pommes de terre');
        $this->moveProductsToCategory(['42603', '42634'], 'Tous les produits');

        $this->addSql('UPDATE category SET parent_id = NULL, position = 0');

        $this->renameCategory('Jus', 'Boissons aromatisées');
        $this->renameCategory('Bobbasan - Bubble Tea', 'Bobbasanss');
        $this->renameCategory('Sodas', 'Soda');
        $this->renameCategory('Chipsan - Chips de Pommes de terre', 'Chipsan - Chips de pommes de terre');
        $this->renameCategory('Négisan - Nouilles instantanées', 'Negisan - Nouilles instantanées');
        $this->renameCategory('Gumisan - Bonbons', 'Gumisan - Sachets de bonbons');
        $this->renameCategory('Yokosan - Céréales', 'Yokosan - Boites de céréales');

        $this->addSql("UPDATE category SET active = 0 WHERE name = 'Bobbasanss'");
        $this->addSql("DELETE FROM category WHERE name IN ('Tout', 'Boissons', 'Épicerie salée', 'Épicerie sucrée', 'Coffrets')");

        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('DROP INDEX IDX_64C19C1727ACA70 ON category');
        $this->addSql('ALTER TABLE category DROP parent_id, DROP position');
    }

    private function insertCategory(string $name, ?string $description, int $position): void
    {
        $this->addSql(
            'INSERT INTO category (name, description, active, position, created_at, updated_at)
             SELECT :name, :description, 1, :position, NOW(), NOW()
             WHERE NOT EXISTS (SELECT 1 FROM category WHERE name = :name)',
            ['name' => $name, 'description' => $description, 'position' => $position],
        );
    }

    /**
     * @param list<string> $references
     */
    private function moveProductsToCategory(array $references, string $categoryName): void
    {
        $this->addSql(
            'UPDATE product
             SET category_id = (SELECT id FROM category WHERE name = :categoryName LIMIT 1)
             WHERE reference IN (:references)',
            ['categoryName' => $categoryName, 'references' => $references],
            ['references' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );
    }

    private function renameCategory(string $oldName, string $newName): void
    {
        $this->addSql(
            'UPDATE category SET name = :newName WHERE name = :oldName',
            ['oldName' => $oldName, 'newName' => $newName],
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
            ['children' => \Doctrine\DBAL\ArrayParameterType::STRING],
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

    private function renameProduct(string|int $reference, string $name): void
    {
        $this->addSql(
            'UPDATE product SET name = :name WHERE reference = :reference',
            ['reference' => (string) $reference, 'name' => $name],
        );
    }
}
