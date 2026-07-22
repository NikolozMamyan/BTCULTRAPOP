<?php

namespace App\Tests\Controller\Admin;

use App\Entity\EmailTemplate;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCustomerControllerTest extends WebTestCase
{
    public function testAdminCanListAndEditCustomers(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseIsUnavailable();

        $suffix = bin2hex(random_bytes(4));
        $adminEmail = sprintf('admin-customer-%s@example.com', $suffix);
        $customerEmail = sprintf('customer-edit-%s@example.com', $suffix);
        $templateName = sprintf('Template emailing %s', $suffix);
        $updatedTemplateName = sprintf('Template modifié %s', $suffix);
        $password = 'admin-password';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        $admin = (new User())
            ->setEmail($adminEmail)
            ->setFirstName('Admin')
            ->setLastName('Client')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, $password));

        $customer = (new User())
            ->setEmail($customerEmail)
            ->setFirstName('Nina')
            ->setLastName('Originale')
            ->setPhone('0600000000')
            ->setLoyaltyPoints(12)
            ->setVerified(false)
            ->setActive(true);
        $customer->setPassword($passwordHasher->hashPassword($customer, 'customer-password'));
        $emailTemplate = (new EmailTemplate())
            ->setName($templateName)
            ->setSubject('Bonjour {{ client.firstName }}')
            ->setHtmlContent('<p>Bonjour {{ client.name }}, découvre nos nouveautés.</p>')
            ->setAudience('custom_selection')
            ->setCreatedBy($admin);

        try {
            $entityManager->persist($admin);
            $entityManager->persist($customer);
            $entityManager->persist($emailTemplate);
            $entityManager->flush();

            $crawler = $client->request('GET', '/profil');
            $token = $crawler->filter('form[action="/auth/login"] input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/auth/login', [
                '_csrf_token' => $token,
                'email' => $adminEmail,
                'password' => $password,
            ]);

            self::assertResponseRedirects('/admin/dashboard');

            $client->request('GET', sprintf('/admin/clients?q=%s', urlencode($customerEmail)));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Clients');
            self::assertSelectorExists('.admin-sidebar__sublink.is-active[href="/admin/clients"]');
            self::assertSelectorTextContains('.admin-customer-table', $customerEmail);
            self::assertSelectorExists(sprintf('a[href="/admin/clients/%d/edit"]', $customer->getId()));

            $crawler = $client->request('GET', sprintf('/admin/clients/%d/edit', $customer->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="customer[firstName]"]');
            self::assertSelectorExists('input[name="customer[email]"]');
            self::assertSelectorExists('input[name="customer[adminRole]"]');

            $formToken = $crawler->filter('input[name="customer[_token]"]')->attr('value');
            $client->request('POST', sprintf('/admin/clients/%d/edit', $customer->getId()), [
                'customer' => [
                    'firstName' => 'Nina',
                    'lastName' => 'Modifiée',
                    'email' => $customerEmail,
                    'phone' => '0611223344',
                    'preferredLocale' => 'fr',
                    'loyaltyPoints' => '42',
                    'verified' => '1',
                    'active' => '1',
                    '_token' => $formToken,
                ],
            ]);

            self::assertResponseRedirects(sprintf('/admin/clients/%d/edit', $customer->getId()));

            $entityManager->clear();
            $updatedCustomer = static::getContainer()->get(UserRepository::class)->loadUserByIdentifier($customerEmail);
            self::assertInstanceOf(User::class, $updatedCustomer);
            self::assertSame('Modifiée', $updatedCustomer->getLastName());
            self::assertSame('0611223344', $updatedCustomer->getPhone());
            self::assertSame(42, $updatedCustomer->getLoyaltyPoints());
            self::assertTrue($updatedCustomer->isVerified());
            self::assertTrue($updatedCustomer->isActive());
            self::assertNotContains('ROLE_ADMIN', $updatedCustomer->getRoles());

            $crawler = $client->request('GET', sprintf('/admin/clients/emailing?template=%d', $emailTemplate->getId()));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Campagnes email');
            self::assertSelectorExists(sprintf(
                '.admin-emailing-template.is-selected[data-admin-emailing-id-param="%d"][data-admin-emailing-name-param]',
                $emailTemplate->getId(),
            ));
            self::assertStringContainsString(
                '{{ client.name }}',
                $crawler->filter('.admin-emailing-template.is-selected')->attr('data-admin-emailing-html-param'),
            );
            self::assertSelectorExists('[data-admin-emailing-variable-param="client.name"]');
            self::assertSelectorExists('[data-admin-emailing-target="selectedList"]');
            self::assertSelectorExists('details.admin-emailing-selection-overview:not([open])');
            self::assertSelectorExists('.admin-emailing-recipient-dropdown > .admin-emailing-recipient-panel');
            self::assertSelectorExists('.admin-emailing[data-admin-emailing-recipient-count-value]');
            self::assertSelectorExists(sprintf(
                'input[data-recipient-email="%s"][data-recipient-variables]',
                $customerEmail,
            ));
            self::assertSelectorExists('button[name="form_action"][value="save"]');
            self::assertSame(
                $templateName,
                $crawler->filter('input[name="template_name"]')->attr('value'),
            );

            $saveForm = $crawler->selectButton('Enregistrer le template')->form([
                'template_name' => $updatedTemplateName,
                'subject' => 'Offre pour {{ client.firstName }}',
                'html_content' => '<p>Bonjour {{ client.name }}, ton email est {{ client.email }}.</p>',
            ]);
            $client->submit($saveForm);
            self::assertResponseRedirects(sprintf(
                '/admin/clients/emailing?template=%d',
                $emailTemplate->getId(),
            ));

            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            self::assertSame($updatedTemplateName, $connection->fetchOne(
                'SELECT name FROM email_template WHERE id = ?',
                [$emailTemplate->getId()],
            ));

            $crawler = $client->request('GET', sprintf('/admin/clients/emailing?template=%d', $emailTemplate->getId()));
            $recipientValue = $crawler
                ->filter(sprintf('input[data-recipient-email="%s"]', $customerEmail))
                ->attr('value');
            self::assertSame((string) $customer->getId(), $recipientValue);
            $sendForm = $crawler->selectButton('Envoyer l’email')->form([
                'template_name' => $updatedTemplateName,
                'subject' => 'Offre pour {{ client.firstName }}',
                'selected_user_ids' => ['1'],
                'html_content' => '<p>Bonjour {{ client.name }}, ton email est {{ client.email }}.</p>',
            ]);
            $client->submit($sendForm);
            self::assertResponseRedirects(sprintf(
                '/admin/clients/emailing?sent=1&template=%d',
                $emailTemplate->getId(),
            ));
            self::assertSame(1, (int) $connection->fetchOne(
                'SELECT recipient_count FROM email_template WHERE id = ?',
                [$emailTemplate->getId()],
            ));
            self::assertNotFalse($connection->fetchOne(
                'SELECT sent_at FROM email_template WHERE id = ?',
                [$emailTemplate->getId()],
            ));
        } finally {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->delete('email_template', ['name' => $updatedTemplateName]);
            $connection->delete('email_template', ['name' => $templateName]);
            $connection->executeStatement('DELETE FROM app_user WHERE email IN (?, ?)', [$adminEmail, $customerEmail]);
        }
    }

    private function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $connection = static::getContainer()->get(Connection::class);
            \assert($connection instanceof Connection);
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf('Database connection is unavailable in test env: %s', $exception->getMessage()));
        }
    }
}
