<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Feedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FeedbackResourceProcessor implements ProcessorInterface
{
    private const ALLOWED_STATUSES = ['new', 'in_progress', 'resolved', 'closed'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * @param FeedbackResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeedbackResource
    {
        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        // Fallback for other operations (GET, GetCollection)
        if (isset($uriVariables['id'])) {
            $feedback = $this->entityManager->getRepository(Feedback::class)->find($uriVariables['id']);
            if (!$feedback) {
                throw new \RuntimeException('Feedback nenalezen');
            }
            return FeedbackResource::createFromEntity($feedback);
        }

        throw new \RuntimeException('Nepodporovaná operace');
    }

    private function handlePost(FeedbackResource $data): FeedbackResource
    {
        if ($data->wants_contact && empty($data->email) && empty($data->phone)) {
            throw new BadRequestHttpException('Pokud požadujete kontaktování, musíte vyplnit e-mail nebo telefonní číslo.');
        }

        $feedback = new Feedback();
        $feedback->setEmail($data->email);
        $feedback->setPhone($data->phone);
        $feedback->setMessage($data->message);
        $feedback->setWantsContact($data->wants_contact);
        $feedback->setStatus('new');

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return FeedbackResource::createFromEntity($feedback);
    }

    private function handlePatch(FeedbackResource $data, array $uriVariables): FeedbackResource
    {
        if (!isset($uriVariables['id'])) {
            throw new \RuntimeException('ID není specifikováno');
        }

        // Validace dat
        $violations = $this->validator->validate($data, null, ['feedback:update']);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            throw new BadRequestHttpException(implode(', ', $errors));
        }

        // Explicitní validace statusu
        if (isset($data->status) && !in_array($data->status, self::ALLOWED_STATUSES)) {
            throw new BadRequestHttpException(sprintf(
                'Status musí být jedna z těchto hodnot: %s', 
                implode(', ', self::ALLOWED_STATUSES)
            ));
        }

        $feedback = $this->entityManager->getRepository(Feedback::class)->find($uriVariables['id']);

        if (!$feedback) {
            throw new \RuntimeException('Feedback nenalezen');
        }

        $feedback = $data->updateEntity($feedback);

        $this->entityManager->flush();

        return FeedbackResource::createFromEntity($feedback);
    }
}
