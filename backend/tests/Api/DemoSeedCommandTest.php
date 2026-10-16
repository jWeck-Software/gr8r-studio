<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeedCommandTest extends ApiTestCase
{
    public function testSeedingAgainReplacesTheDemoWorkspaceWithAFreshCopy(): void
    {
        $this->signIn();
        $this->postJson('/api/sync', ['ops' => [['op' => 'delete', 'type' => 'tasks', 'id' => 't4']]]);

        $command = new CommandTester((new Application(self::$kernel))->find('app:demo:seed'));
        $command->execute([]);

        $command->assertCommandIsSuccessful();
        $this->signIn();
        $tasks = $this->getJson('/api/bootstrap')['data']['tasks'];
        self::assertCount(48, $tasks);
        self::assertContains('t4', array_column($tasks, 'id'));
    }
}
