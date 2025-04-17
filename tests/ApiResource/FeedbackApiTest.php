<?php

namespace App\Tests\ApiResource;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\FeedbackResource;
use App\ApiResource\FeedbackResourceProcessor;
use App\Entity\Feedback;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeedbackApiTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private FeedbackResourceProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->processor = new FeedbackResourceProcessor($this->entityManager, $this->validator);
    }

    public function testPostRequestValidData(): void
    {
        // Připrav testovací data
        $resource = new FeedbackResource();
        $resource->email = 'test@example.com';
        $resource->message = 'Testovací zpráva';
        $resource->wants_contact = true;

        // Očekáváme, že bude vytvořena nová entita
        $expectedFeedback = new Feedback();
        
        // Připrav mock chování
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function($feedback) use ($resource) {
                $this->assertEquals($resource->email, $feedback->getEmail());
                $this->assertEquals($resource->message, $feedback->getMessage());
                $this->assertEquals($resource->wants_contact, $feedback->isWantsContact());
                $this->assertEquals('new', $feedback->getStatus());
                return true;
            }));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Mock repository pro vytvoření response
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);

        // Spusť testovanou metodu
        $operation = new Post(provider: 'some_provider', processor: 'some_processor');
        $result = $this->processor->process($resource, $operation);

        // Ověř výsledek
        $this->assertInstanceOf(FeedbackResource::class, $result);
    }
    
    public function testPostRequestWithoutContactInfo(): void
    {
        // Připrav testovací data - chybí email a telefon, ale wants_contact je true
        $resource = new FeedbackResource();
        $resource->message = 'Testovací zpráva';
        $resource->wants_contact = true;
        $resource->email = null;
        $resource->phone = null;

        // Očekáváme výjimku
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Pokud požadujete kontaktování, musíte vyplnit e-mail nebo telefonní číslo.');

        // Spusť testovanou metodu
        $operation = new Post(provider: 'some_provider', processor: 'some_processor');
        $this->processor->process($resource, $operation);
    }

    public function testPatchRequestValidData(): void
    {
        // Připrav testovací data
        $resource = new FeedbackResource();
        $resource->status = 'resolved';
        $resource->contacted = true;
        $resource->internal_note = 'Poznámka pro interní účely';

        // Vytvoř existující entitu
        $existingFeedback = new Feedback();
        $existingFeedback->setMessage('Původní zpráva');
        $existingFeedback->setStatus('new');
        $existingFeedback->setContacted(false);
        
        // Připrav mock repository
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($existingFeedback);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);
        
        // Validátor by neměl najít žádné chyby
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());
            
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Spusť testovanou metodu
        $operation = new Patch(provider: 'some_provider', processor: 'some_processor');
        $result = $this->processor->process($resource, $operation, ['id' => 123]);

        // Ověř, že entita byla správně aktualizována
        $this->assertEquals('resolved', $existingFeedback->getStatus());
        $this->assertEquals(true, $existingFeedback->isContacted());
        $this->assertEquals('Poznámka pro interní účely', $existingFeedback->getInternalNote());
        
        // Ověř výsledek
        $this->assertInstanceOf(FeedbackResource::class, $result);
    }
    
    public function testPatchRequestInvalidStatus(): void
    {
        // Připrav testovací data s neplatným statusem
        $resource = new FeedbackResource();
        $resource->status = 'invalid_status';

        // Vytvoř existující entitu
        $existingFeedback = new Feedback();
        $existingFeedback->setMessage('Testovací zpráva');
        
        // Připrav mock repository
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')
            ->with(123)
            ->willReturn($existingFeedback);
        
        $this->entityManager->method('getRepository')
            ->willReturn($repository);
        
        // Validátor by neměl najít žádné chyby v základních validacích
        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList());

        // Očekáváme výjimku kvůli neplatnému statusu
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Status musí být jedna z těchto hodnot: new, in_progress, resolved, closed');

        // Spusť testovanou metodu
        $operation = new Patch(provider: 'some_provider', processor: 'some_processor');
        $this->processor->process($resource, $operation, ['id' => 123]);
    }
    
    public function testPatchRequestValidationErrors(): void
    {
        // Připrav testovací data
        $resource = new FeedbackResource();
        $resource->status = 'resolved';

        // Vytvoř existující entitu
        $existingFeedback = new Feedback();
        $existingFeedback->setMessage('Testovací zpráva');
        
        // Připrav mock repository
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')
            ->with(123)
            ->willReturn($existingFeedback);
        
        $this->entityManager->method('getRepository')
            ->willReturn($repository);
        
        // Validátor by měl najít chyby
        $violation = new ConstraintViolation(
            'Neplatná hodnota', // message
            'Neplatná hodnota', // messageTemplate
            [], // parameters
            null, // root
            'status', // propertyPath
            'resolved', // invalidValue
            null, // plural
            null, // code
            null, // constraint
            null // cause
        );
        
        $violationList = new ConstraintViolationList([$violation]);
        
        $this->validator->method('validate')
            ->willReturn($violationList);

        // Očekáváme výjimku kvůli validačním chybám
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Neplatná hodnota');

        // Spusť testovanou metodu
        $operation = new Patch(provider: 'some_provider', processor: 'some_processor');
        $this->processor->process($resource, $operation, ['id' => 123]);
    }
} 