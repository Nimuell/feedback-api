<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Feedback;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Feedback',
    operations: [
        new Post(
            uriTemplate: '/feedback',
            denormalizationContext: ['groups' => ['feedback:create']],
            security: 'is_granted("PUBLIC_ACCESS")',
            validationContext: ['groups' => ['feedback:create']],
            provider: FeedbackResourceProvider::class,
            processor: FeedbackResourceProcessor::class
        ),
        new GetCollection(
            uriTemplate: '/admin/feedback',
            normalizationContext: ['groups' => ['feedback:read']],
            security: 'is_granted("ROLE_ADMIN")',
            provider: FeedbackResourceProvider::class
        ),
        new Get(
            uriTemplate: '/admin/feedback/{id}',
            normalizationContext: ['groups' => ['feedback:read', 'feedback:item:read']],
            security: 'is_granted("ROLE_ADMIN")',
            provider: FeedbackResourceProvider::class
        ),
        new Patch(
            uriTemplate: '/admin/feedback/{id}',
            normalizationContext: ['groups' => ['feedback:read', 'feedback:item:read']],
            denormalizationContext: ['groups' => ['feedback:update']],
            security: 'is_granted("ROLE_ADMIN")',
            validationContext: ['groups' => ['feedback:update']],
            provider: FeedbackResourceProvider::class,
            processor: FeedbackResourceProcessor::class
        )
    ]
)]
class FeedbackResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['feedback:read'])]
    public ?int $id = null;

    #[Groups(['feedback:read', 'feedback:create'])]
    #[Assert\Email(groups: ['feedback:create'])]
    #[Assert\Expression(
        "this.wants_contact == false or (value != null or this.phone != null)",
        message: "Pokud požadujete kontaktování, musíte vyplnit e-mail nebo telefonní číslo.",
        groups: ['feedback:create']
    )]
    public ?string $email = null;

    #[Groups(['feedback:read', 'feedback:create'])]
    #[Assert\Regex(
        pattern: '/^\+[1-9]\d{1,14}$/',
        message: 'Telefonní číslo musí být ve formátu E.164 (např. +420123456789)',
        groups: ['feedback:create']
    )]
    #[Assert\Expression(
        "this.wants_contact == false or (value != null or this.email != null)",
        message: "Pokud požadujete kontaktování, musíte vyplnit e-mail nebo telefonní číslo.",
        groups: ['feedback:create']
    )]
    public ?string $phone = null;

    #[Groups(['feedback:read', 'feedback:create'])]
    #[Assert\NotBlank(message: 'Zpráva je povinná', groups: ['feedback:create'])]
    public string $message;

    #[Groups(['feedback:read', 'feedback:create'])]
    public bool $wants_contact = false;

    #[Groups(['feedback:read', 'feedback:update'])]
    public bool $contacted = false;

    #[Groups(['feedback:read', 'feedback:update'])]
    public string $status = 'new';

    #[Groups(['feedback:read', 'feedback:update'])]
    public ?string $internal_note = null;

    #[Groups(['feedback:read'])]
    public ?\DateTimeImmutable $created_at = null;

    #[Groups(['feedback:read'])]
    public ?\DateTimeImmutable $updated_at = null;

    public static function createFromEntity(Feedback $feedback): self
    {
        $resource = new self();
        $resource->id = $feedback->getId();
        $resource->email = $feedback->getEmail();
        $resource->phone = $feedback->getPhone();
        $resource->message = $feedback->getMessage();
        $resource->wants_contact = $feedback->isWantsContact();
        $resource->contacted = $feedback->isContacted();
        $resource->status = $feedback->getStatus();
        $resource->internal_note = $feedback->getInternalNote();
        $resource->created_at = $feedback->getCreatedAt();
        $resource->updated_at = $feedback->getUpdatedAt();

        return $resource;
    }

    public function updateEntity(Feedback $feedback): Feedback
    {
        if (isset($this->contacted)) {
            $feedback->setContacted($this->contacted);
        }

        if (isset($this->status)) {
            $feedback->setStatus($this->status);
        }

        if (isset($this->internal_note)) {
            $feedback->setInternalNote($this->internal_note);
        }

        return $feedback;
    }
}
