<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import legacy customers into app_user.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('app_user')) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $seenEmails = [];

        foreach ($this->customers() as $customer) {
            $email = mb_strtolower(trim((string) $customer['email']));

            if ('' === $email || isset($seenEmails[$email])) {
                continue;
            }

            $seenEmails[$email] = true;

            $this->addSql(
                'INSERT INTO app_user (
                    email,
                    first_name,
                    last_name,
                    roles,
                    password,
                    phone,
                    avatar_filename,
                    loyalty_points,
                    preferred_locale,
                    verified,
                    active,
                    last_login_at,
                    created_at,
                    updated_at
                )
                SELECT ?, ?, ?, ?, ?, NULL, NULL, 0, ?, 1, 1, NULL, ?, ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM app_user WHERE email = ?
                )',
                [
                    $email,
                    trim((string) $customer['prenom']),
                    trim((string) $customer['nom']),
                    '[]',
                    trim((string) $customer['password_hash']),
                    'fr',
                    $now,
                    $now,
                    $email,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->write('No rollback for imported customers to avoid deleting pre-existing or active customer accounts.');
    }

    /**
     * @return list<array{nom: string, prenom: string, email: string, password_hash: string}>
     */
    private function customers(): array
    {
        $json = <<<'JSON'
[
{"nom":"Aida","prenom":"Afg","email":"aidafreireginer@gmail.com","password_hash":"$2y$10$cmomL6n1.NkCQRnEr7aN1.ojZvjPby62n7fl6QatdvGirvMjBE5KS"},
{"nom":"Alberny","prenom":"Julien","email":"alberny.julien@gmail.com","password_hash":"$2y$10$R81Lbvg8ZTjxyHF7CJxVSuoOxagVPzbpvuZYGr.rvvpjqdfkazSSq"},
{"nom":"Allison ","prenom":"Walczak ","email":"RetroAnimeCo@proton.me","password_hash":"$2y$10$HMyfuC7A2WzEDXamsxEv2uWPVOfzO\/hQXQU5\/2AJfIJVOw17svFEC"},
{"nom":"Bfj","prenom":"L","email":"Sasa.jada15@gmail.com","password_hash":"$2y$10$pILOqCd2fHDQ.FB6nlPZ0eNx8RNNu10oTwxXE36BizzpOZ.NxpykG"},
{"nom":"Bouassida","prenom":"Aisam","email":"info@otaking.nl","password_hash":"$2y$10$NdKbAst.cVuvP1k8tMxTweHqtJNOObdobFlYvjA.EcIQqQLOJUkqG"},
{"nom":"candelier","prenom":"mathieu","email":"roman28880@gmail.com","password_hash":"$2y$10$N5aK9uYxOdguemS2sxoWcunzHhlZRueh.yOBkuU7ppDrERlWeUDnK"},
{"nom":"Capestrani","prenom":"Lorenzo Aaron","email":"capestrani00@gmail.com","password_hash":"$2y$10$dbgnhsF7q0OwBxagAzC6MeRfaqYyD8feVVVs9Rz0jriOffiBpzYQ6"},
{"nom":"CELESTIN","prenom":"Florian","email":"florian.celestin31@gmail.com","password_hash":"$2y$10$L1634zMMlUDdFbBnON2DyeDHpcqqrD09eDaXEMTW43.8yDhVIXT1e"},
{"nom":"Charrier","prenom":"Alexandre","email":"alex.charrier89@gmail.com","password_hash":"$2y$10$rbN\/zzl9VCGCO4NYVSgA0.h4K\/FEvI3u5d2JrN3XUKmMihUTYmqni"},
{"nom":"Clarke","prenom":"Lara","email":"lukacorsss@gmail.com","password_hash":"$2y$10$ObkWXiI7h3aXn7dyS1OEGuUVajbQ39OZmvtGMX1GHzBk\/QMkbIhbK"},
{"nom":"Daniel","prenom":"Carlos ","email":"c.danieljustino@gmail.com","password_hash":"$2y$10$2YyyUi.aIP7HGtfJaaKiE.IbECxEfH77oTB3c3MOojB5Trhd6p9xm"},
{"nom":"Dearborn","prenom":"Danielle","email":"danny.dearborn1230@gmail.com","password_hash":"$2y$10$k\/B29Ry5CH3D5IpnQVqVcuPFj6t47Qd33DHh24C92\/UCYfkw8Q0w6"},
{"nom":"Dutheil","prenom":"Lara","email":"gamerlara9@gmail.com","password_hash":"$2y$10$hW6Glue3okJgmTao\/U90x.bJXYjCBeX\/4gFdK8xrI01aAykcilUOS"},
{"nom":"Emeraude ","prenom":"Fleetway ","email":"fleetwayemeraude@gmail.com","password_hash":"$2y$10$7MYnTD.ueZbU09AavSLUB.Oh.FqVpSK7WUl2eseQkVfDgiimdW2mC"},
{"nom":"ets","prenom":"test","email":"developer1607@gmail.com","password_hash":"$2y$10$6FzhPU4CRZt1EPFK7gxj6.6kDnhR3ld046JRRkwCoxtAF8sE10qTa"},
{"nom":"Fargier","prenom":"Manon","email":"slaussel@gmail.com","password_hash":"$2y$10$r0On4.SMGkUHCpJEKr\/mauhw6H6pT4PEUnmncHg0ceCaCvhE\/K8jO"},
{"nom":"Fossard","prenom":"Oceane","email":"oceane.fossard1@gmail.com","password_hash":"$2y$10$rEAtBb7uNjLzmyVHdNzh0uIXwOa8jfhM2P2fKrxKkgcJ04UB9UYnO"},
{"nom":"frazzetta","prenom":"Nunziata","email":"nunziata.frazzetta@gmail.com","password_hash":"$2y$10$e.2JUzyR6eRq.J881739g.qudETinQ41QZTAvjs.NYwpma5wyk\/Iq"},
{"nom":"Gallin","prenom":"Malo","email":"Malogln38@gmail.com","password_hash":"$2y$10$KujNhmzek2ByqZJbJYZXpOTkhAG3gtBpoge\/y4iZ3HoPtdj9UYGr2"},
{"nom":"Ganichon","prenom":"Aurélie","email":"aurelieganichon53@gmail.com","password_hash":"$2y$10$5Z7Rm7IxYIIsg.JubePhVuVO457AuMBLnDWR5\/EyGuwRhCgR8ShSW"},
{"nom":"García Catarineu ","prenom":"Saúl","email":"saul99pro@gmail.com","password_hash":"$2y$10$ehzJiry4.bSgldtAl90ugeBhvcRcPCfUbYHlb6XqFADK.gDHZiGeW"},
{"nom":"Goated","prenom":"Ray","email":"raydiscord525@gmail.com","password_hash":"$2y$10$xtfJ6ebi97WVpPCvro1qBeEGY7T3Q1jYzZl0Pbg7XylYXtRfj0QS6"},
{"nom":"Guerroum","prenom":"Yassine","email":"guerroum.tfa@gmail.com","password_hash":"$2y$10$3B3OMT5AyO7DTXeiEJ50c.WRkmkn55C\/Nj66IS1LcBsIcOR4nNZ7S"},
{"nom":"HAAG","prenom":"Noémie","email":"nhaaglnstrade@gmail.com","password_hash":"$2y$10$r5\/LqInLufovwEFY3dwcOO27mCUA3QZdKW83STvqa2JEYOXs.4M8G"},
{"nom":"HANAY","prenom":"Hanay","email":"hnayrr@gmail.com","password_hash":"$2y$10$RuMihN1wquPILxMfcGw23.r.3SWV4o3lS3rcLb22XxT2UURV4UtBq"},
{"nom":"Huang","prenom":"Zhifu","email":"hmparis2022@yahoo.com","password_hash":"$2y$10$S5tnyUJGvOTCpTjla.HmNeq1xVlEvQU6wXjall.us5hSit5j2pmRG"},
{"nom":"huh","prenom":"Mrk","email":"omaghhaiag@gmail.com","password_hash":"$2y$10$FpmlsPsXEAM\/Fm31jSIFxe70Q4us.yLOxmCP0f4DXE.RNkx6MKkXu"},
{"nom":"Huile","prenom":"Ju","email":"onigumoyc1@proton.me","password_hash":"$2y$10$PDFSIOIRv.BPYaw7RCLRj.3ymt8hMn4hFAL8unzuNJPmyRGMa8\/HK"},
{"nom":"IGHIT","prenom":"Said","email":"Ighit.nouria@orange.fr","password_hash":"$2y$10$2xKiY6AWVxa2MvER3g6wL.yV9x01iH5dscx\/n2PzMQ67AQD9lSI4y"},
{"nom":"Jojo","prenom":"Jojo","email":"souriana7606@gmail.com","password_hash":"$2y$10$pF7o06sa4PwSdv\/oV5ABNO34WPkteWeN.4ysjXu4fpuALBRvTEL3e"},
{"nom":"Lanio","prenom":"Justin","email":"juju.lanio@hotmail.fr","password_hash":"$2y$10$iV\/TNwcEKpFwuyriZfanXeCEzRocwUwn\/\/Zd4I48GYBSKjlZ5jHzu"},
{"nom":"Lazar ","prenom":"Luca","email":"lazarluca98@gmail.com","password_hash":"$2y$10$EpGe1RrdERZABFr0A9D4veZ0tmUfDyH5\/vsWsTOvGJ.LNasQRzBmy"},
{"nom":"Maffre","prenom":"Zoan","email":"zoanmaffre@icloud.com","password_hash":"$2y$10$iagAM4jcWcQK6f5bVnKiUeCvX.6bP0FEuhBAd65S5tNDq47bOHK4a"},
{"nom":"martot","prenom":"aurelien","email":"auremartot210@gmail.com","password_hash":"$2y$10$an.Zp8XD6HDlZd2bcDp3Zu0KD3GQYHNHGD\/jD9OpDEWkJc6jaNqvK"},
{"nom":"Meneghelli","prenom":"Meneghelli","email":"sicilienne1991@hotmail.fr","password_hash":"$2y$10$IVPwAbSFOmp9.5V7W2HW.eRFeepDXtQEIucy9lRJBSUAKyOhUjQlW"},
{"nom":"Milia","prenom":"Matthis","email":"matthis-76@hotmail.fr","password_hash":"$2y$10$ZHsyhTzkd5RPdFu8XglBReZpcyU.xAoVxRYobpQOAc174bdwBjJsy"},
{"nom":"Moyaux","prenom":"Patrick","email":"patrick.moyaux@gmail.com","password_hash":"$2y$10$p73mSInsT4BlCUlbHd6r8uB6L1XCb6bE2QqLEPfxD8yb6CSB.iLnO"},
{"nom":"NERON","prenom":"ANGÉLIQUE","email":"lesbonbonsdange@outlook.fr","password_hash":"$2y$10$TJHVCa6CyK70ys8y9DBf6.PTzGcUBeHjAYTVZmLq9.mY6A4jZXOIm"},
{"nom":"Olmeta ","prenom":"Philippe ","email":"philippe.olmeta@sfr.fr","password_hash":"$2y$10$x1qso\/ShaNjziJkl5.oxVeD4k7KiGBQnEMkd\/RnBPxVEPAAjDwf1u"},
{"nom":"Pedro","prenom":"Pereira","email":"pedropereira1305@hotmail.com","password_hash":"$2y$10$UVyVWlKomThowf88t9uu2.zuHXz5z0dIJr7OFBf2BHNJ2ihQfEBn6"},
{"nom":"Roig","prenom":"Andreu","email":"andreuonline@hotmail.com","password_hash":"$2y$10$mPj4ociZ1jXiWRntlGcbuOGKGBtIyqJF6iBooi74MGZuMyqxGfENS"},
{"nom":"Sanja ","prenom":"Stankovic ","email":"sanjastankovic280@gmail.com","password_hash":"$2y$10$DC9lEeKXBayMiSLDoOYA0uL3SiItXEiCc32Iob.H7Bea7Lf3c7Av6"},
{"nom":"Schibrowsky","prenom":"Marten","email":"thecookiesquadr6@gmail.com","password_hash":"$2y$10$1LkKjmGIm21Eso9nvXZwE.Efyhin.whvf98FD41p20A.dujENJ1DO"},
{"nom":"Schuster","prenom":"Christopher","email":"christopher.schuster@gmx.de","password_hash":"$2y$10$xiYnbzqek6pPyJFEBlnfNe6W7SdPabULKqkXbu8Zh.Oq546QGqZqq"},
{"nom":"Schuster","prenom":"Christopher","email":"christopher.schuster@gmx.de","password_hash":"$2y$10$NetqLMctIjVbZMHD5RSmUu28Z\/ha9d7PhgxLtMFJ\/IMQpfHe85hNK"},
{"nom":"Travassos","prenom":"luis","email":"luistrava86@hotmail.com","password_hash":"$2y$10$irM1WlybCrSuUp3xYq3iuu6NiEDgXnfXhpzWs\/IYUabkCLlpY7dfW"},
{"nom":"VO","prenom":"Hoang Mai Hoa Christelle","email":"vhmhchristelle@laposte.net","password_hash":"$2y$10$vAy\/ZGKOSimkhRgmk8CrDOpvOR9idsl4e7LJKMCqZobNccCA6XF2q"},
{"nom":"Voicu","prenom":"Alexandra","email":"stela_alex_87@yahoo.com","password_hash":"$2y$10$80Ety4i0drWHmA9UyJrsz.t644.iV5GXacSCku\/am4AgN.wQQxZ5K"},
{"nom":"Webster ","prenom":"Molly ","email":"molly.web17@icloud.com","password_hash":"$2y$10$ilkW2EsfIyjAAHdsSRNN7ubkjsucsPZf5WPhuh9C6XSDfA2aOHkJO"},
{"nom":"Wittkowski","prenom":"Thaddeus","email":"handyac66@gmail.com","password_hash":"$2y$10$VO9Qr.8KWxMcTleiLhesCu5UZ4oYd4IzAS2a66Oola2ugEWJTja\/S"}
]
JSON;

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
