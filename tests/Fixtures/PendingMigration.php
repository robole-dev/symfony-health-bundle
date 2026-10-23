<?php

declare(strict_types=1);

namespace Robole\SymfonyHealthBundle\Tests\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class PendingMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        throw new \LogicException('The health check must never execute a migration.');
    }

    public function down(Schema $schema): void
    {
        throw new \LogicException('The health check must never execute a migration.');
    }
}
