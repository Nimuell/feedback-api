<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Feedback;
use PHPUnit\Framework\TestCase;

class FeedbackTest extends TestCase
{
    public function testCreateEmptyFeedback(): void
    {
        $feedback = new Feedback();
        
        $this->assertNull($feedback->getId());
        $this->assertNull($feedback->getEmail());
        $this->assertNull($feedback->getPhone());
        $this->assertFalse($feedback->isWantsContact());
        $this->assertFalse($feedback->isContacted());
        $this->assertEquals('new', $feedback->getStatus());
        $this->assertNull($feedback->getInternalNote());
    }
    
    public function testSettersAndGetters(): void
    {
        $feedback = new Feedback();
        
        // Nastavení a získání hodnot
        $email = 'test@example.com';
        $feedback->setEmail($email);
        $this->assertEquals($email, $feedback->getEmail());
        
        $phone = '+420123456789';
        $feedback->setPhone($phone);
        $this->assertEquals($phone, $feedback->getPhone());
        
        $message = 'Testovací zpráva';
        $feedback->setMessage($message);
        $this->assertEquals($message, $feedback->getMessage());
        
        $feedback->setWantsContact(true);
        $this->assertTrue($feedback->isWantsContact());
        
        $feedback->setContacted(true);
        $this->assertTrue($feedback->isContacted());
        
        $status = 'resolved';
        $feedback->setStatus($status);
        $this->assertEquals($status, $feedback->getStatus());
        
        $note = 'Interní poznámka';
        $feedback->setInternalNote($note);
        $this->assertEquals($note, $feedback->getInternalNote());
        
        $now = new \DateTimeImmutable();
        $feedback->setCreatedAt($now);
        $this->assertEquals($now, $feedback->getCreatedAt());
        
        $updated = new \DateTimeImmutable();
        $feedback->setUpdatedAt($updated);
        $this->assertEquals($updated, $feedback->getUpdatedAt());
    }
    
    public function testLifecycleCallbacks(): void
    {
        $feedback = new Feedback();
        
        // Testování PrePersist
        $reflection = new \ReflectionObject($feedback);
        $method = $reflection->getMethod('onPrePersist');
        $method->setAccessible(true);
        $method->invoke($feedback);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $feedback->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $feedback->getUpdatedAt());
        $originalUpdated = $feedback->getUpdatedAt();
        
        // Testování PreUpdate
        sleep(1); // Počkáme 1 sekundu, aby byl vidět rozdíl v časech
        $method = $reflection->getMethod('onPreUpdate');
        $method->setAccessible(true);
        $method->invoke($feedback);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $feedback->getUpdatedAt());
        $this->assertNotEquals($originalUpdated, $feedback->getUpdatedAt());
    }
} 