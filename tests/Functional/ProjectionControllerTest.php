<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectionControllerTest extends WebTestCase
{
    #[Test]
    public function the_form_is_reachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/quand-partir');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="morning_start"]');
    }

    #[Test]
    public function it_computes_the_leave_time_from_the_morning(): void
    {
        $client = static::createClient();
        $client->request('POST', '/quand-partir', [
            'morning_start' => '08:48',
            'lunch_departure' => '11:47',
            'lunch_return' => '12:13',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.leave', '16:42');
    }

    #[Test]
    public function an_unreadable_time_is_reported_without_crashing(): void
    {
        $client = static::createClient();
        $client->request('POST', '/quand-partir', [
            'morning_start' => 'midi',
            'lunch_departure' => '11:47',
            'lunch_return' => '12:13',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.error');
    }
}
