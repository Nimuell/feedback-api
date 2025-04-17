<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Feedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FeedbackResourceProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $repository = $this->entityManager->getRepository(Feedback::class);
        
        // Jednotlivý záznam
        if ($operation instanceof \ApiPlatform\Metadata\Get) {
            $feedback = $repository->find($uriVariables['id']);
            
            if (!$feedback) {
                throw new NotFoundHttpException('Feedback nenalezen');
            }
            
            return FeedbackResource::createFromEntity($feedback);
        }
        
        // Kolekce záznamů
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            $feedbacks = $repository->findBy([], ['created_at' => 'DESC']);
            $resources = [];
            
            foreach ($feedbacks as $feedback) {
                $resources[] = FeedbackResource::createFromEntity($feedback);
            }
            
            return $resources;
        }
        
        // POST a PATCH operace řeší procesor
        return new FeedbackResource();
    }
} 