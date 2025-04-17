<?php

namespace App\Tests\ApiResource;

use App\Entity\Feedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FeedbackControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->apiKey = self::getContainer()->getParameter('app.api_key');
        
        // Vyčistit databázi před každým testem
        $this->clearFeedbackTable();
    }
    
    private function clearFeedbackTable(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        
        $connection->executeStatement($platform->getTruncateTableSQL('feedback', true));
    }

    public function testPostFeedback(): void
    {
        $payload = [
            'email' => 'test@example.com',
            'message' => 'Testovací zpráva od uživatele',
            'wants_contact' => true
        ];
        
        // Odeslat POST request bez autentizace (veřejné API)
        $this->client->request(
            'POST',
            '/api/feedback',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Zkontrolovat response
        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        // Kontrola, zda byly správně uloženy data
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals($payload['email'], $responseData['email']);
        $this->assertEquals($payload['message'], $responseData['message']);
        $this->assertEquals($payload['wants_contact'], $responseData['wants_contact']);
        $this->assertEquals('new', $responseData['status']);
        $this->assertFalse($responseData['contacted']);
        
        // Zkontrolovat, že záznam existuje v databázi
        $feedback = $this->entityManager->getRepository(Feedback::class)->find($responseData['id']);
        $this->assertNotNull($feedback);
        $this->assertEquals($payload['email'], $feedback->getEmail());
    }
    
    public function testPostFeedbackValidationError(): void
    {
        // Chybí povinné pole message
        $payload = [
            'email' => 'test@example.com',
            'wants_contact' => true
        ];
        
        $this->client->request(
            'POST',
            '/api/feedback',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Zkontrolovat error response
        $this->assertResponseStatusCodeSame(422); // Unprocessable Entity
    }
    
    public function testPostFeedbackWithoutContactInfo(): void
    {
        // Požaduje kontakt, ale chybí email i telefon
        $payload = [
            'message' => 'Testovací zpráva',
            'wants_contact' => true
        ];
        
        $this->client->request(
            'POST',
            '/api/feedback',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Zkontrolovat error response
        $this->assertResponseStatusCodeSame(400); // Bad Request
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('kontaktování', $responseData['message']);
    }
    
    public function testPatchFeedback(): void
    {
        // 1. Nejprve vytvořit feedback
        $feedback = new Feedback();
        $feedback->setEmail('test@example.com');
        $feedback->setMessage('Původní zpráva');
        $feedback->setStatus('new');
        
        $this->entityManager->persist($feedback);
        $this->entityManager->flush();
        
        $id = $feedback->getId();
        
        // 2. Nyní aktualizovat pomocí PATCH
        $updateData = [
            'status' => 'resolved',
            'contacted' => true,
            'internal_note' => 'Vyřešeno telefonicky'
        ];
        
        $this->client->request(
            'PATCH',
            '/api/admin/feedback/' . $id,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->apiKey
            ],
            json_encode($updateData)
        );
        
        // Zkontrolovat response
        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        // Kontrola, zda byly správně aktualizovány data
        $this->assertEquals($id, $responseData['id']);
        $this->assertEquals($updateData['status'], $responseData['status']);
        $this->assertEquals($updateData['contacted'], $responseData['contacted']);
        $this->assertEquals($updateData['internal_note'], $responseData['internal_note']);
        
        // Zkontrolovat, že záznam byl aktualizován v databázi
        $updatedFeedback = $this->entityManager->getRepository(Feedback::class)->find($id);
        $this->assertEquals($updateData['status'], $updatedFeedback->getStatus());
        $this->assertEquals($updateData['contacted'], $updatedFeedback->isContacted());
    }
    
    public function testPatchFeedbackWithoutAuthentication(): void
    {
        // Vytvořit feedback
        $feedback = new Feedback();
        $feedback->setEmail('test@example.com');
        $feedback->setMessage('Testovací zpráva');
        
        $this->entityManager->persist($feedback);
        $this->entityManager->flush();
        
        $id = $feedback->getId();
        
        // Aktualizovat bez autorizačního tokenu
        $updateData = [
            'status' => 'resolved'
        ];
        
        $this->client->request(
            'PATCH',
            '/api/admin/feedback/' . $id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json'],
            json_encode($updateData)
        );
        
        // Mělo by vrátit 401 Unauthorized
        $this->assertResponseStatusCodeSame(401);
    }
    
    public function testPatchFeedbackWithInvalidStatus(): void
    {
        // Vytvořit feedback
        $feedback = new Feedback();
        $feedback->setEmail('test@example.com');
        $feedback->setMessage('Testovací zpráva');
        
        $this->entityManager->persist($feedback);
        $this->entityManager->flush();
        
        $id = $feedback->getId();
        
        // Aktualizovat s neplatným statusem
        $updateData = [
            'status' => 'invalid_status'
        ];
        
        $this->client->request(
            'PATCH',
            '/api/admin/feedback/' . $id,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->apiKey
            ],
            json_encode($updateData)
        );
        
        // Mělo by vrátit 400 Bad Request
        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Status musí být', $responseData['message']);
    }
} 