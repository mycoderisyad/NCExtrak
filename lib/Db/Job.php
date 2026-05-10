<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Db;

use OCP\AppFramework\Db\Entity;

class Job extends Entity
{
    protected ?int $id = null;
    protected string $uid = '';
    protected int $sourceFileId = 0;
    protected string $state = 'queued';
    protected int $progress = 0;
    protected ?string $error = null;
    protected string $targetFolder = '';
    protected bool $overwrite = false;
    protected ?string $resultPayload = null;
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('sourceFileId', 'integer');
        $this->addType('progress', 'integer');
        $this->addType('overwrite', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getSourceFileId(): int
    {
        return $this->sourceFileId;
    }

    public function setSourceFileId(int $sourceFileId): self
    {
        $this->sourceFileId = $sourceFileId;
        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): self
    {
        $this->progress = $progress;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getTargetFolder(): string
    {
        return $this->targetFolder;
    }

    public function setTargetFolder(string $targetFolder): self
    {
        $this->targetFolder = $targetFolder;
        return $this;
    }

    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }

    public function setOverwrite(bool $overwrite): self
    {
        $this->overwrite = $overwrite;
        return $this;
    }

    public function getResultPayload(): ?string
    {
        return $this->resultPayload;
    }

    public function setResultPayload(?string $resultPayload): self
    {
        $this->resultPayload = $resultPayload;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
