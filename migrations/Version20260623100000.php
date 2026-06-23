<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623100000 extends AbstractMigration
{
    /**
     * @var array<string, string>
     */
    private const EANS_BY_REFERENCE = [
        '18008' => '3770015056008',
        '18077' => '3770015056077',
        '28545' => '3770027194545',
        '28569' => '3770027194569',
        '28903' => '3770027194903',
        '28927' => '3770027194927',
        '28965' => '3770027194965',
        '28989' => '3770027194989',
        '32112' => '3770033234112',
        '32143' => '3770033234143',
        '32174' => '3770033234174',
        '32204' => '3770033234204',
        '32235' => '3770033234235',
        '32266' => '3770033234266',
        '32297' => '3770033234297',
        '33411' => '3760412580411',
        '33442' => '3760412580442',
        '33473' => '3760412580473',
        '33503' => '3760412580503',
        '33534' => '3760412580534',
        '40743' => '3770027194743',
        '40767' => '3770027194767',
        '41705' => '3770027194705',
        '41729' => '3770027194729',
        '50320' => '3770030630320',
        '60144' => '3770032049144',
        '60894' => '3770030630894',
        '60924' => '3770030630924',
        '61832' => '3770030630832',
        '61863' => '3770030630863',
        '80389' => '3770033234389',
        '80419' => '3770033234419',
        '80440' => '3770033234440',
    ];

    public function getDescription(): string
    {
        return 'Complete verified product EAN-13 values and move the Goku Bubble Tea EAN to its active product record.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE product
             SET ean = NULL
             WHERE reference = '39389'
               AND ean = '3770033234389'",
        );

        foreach (self::EANS_BY_REFERENCE as $reference => $ean) {
            $this->addSql(
                "UPDATE product
                 SET ean = :ean
                 WHERE reference = :reference
                   AND (ean IS NULL OR ean = '')",
                ['reference' => (string) $reference, 'ean' => $ean],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Previous EAN values cannot be reconstructed reliably across environments.',
        );
    }
}
