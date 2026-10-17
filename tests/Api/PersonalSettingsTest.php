<?php

namespace App\Tests\Api;

/**
 * A Member's Preferences, view settings, notification settings and recent searches are their own:
 * they follow them to every browser and change nothing for anyone else. Sarah is an Admin.
 */
final class PersonalSettingsTest extends ApiTestCase
{
    private const array PREFS = [
        'theme' => 'dark',
        'accent' => 'teal',
        'side' => 'compact',
        'density' => 'compact',
        'weekStart' => 0,
        'dateFmt' => 'd MMM',
        'lang' => 'Deutsch',
        'tz' => '(GMT+01:00) Berlin',
        'motion' => 'reduce',
        'home' => 'mytasks',
        'openTasks' => 'full',
        'defaultTab' => 'list',
        'confirmDel' => false,
    ];

    public function testTheOwnersPreferencesFollowThemToAnotherBrowser(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => 'prefs', 'data' => self::PREFS]]);

        self::assertSame(['ok' => true], $body);
        $this->client->getCookieJar()->clear();
        $this->signIn();
        self::assertRecord(self::PREFS, $this->getJson('/api/bootstrap')['prefs']);
    }

    public function testSarahKeepsHerOwnPreferences(): void
    {
        $this->signIn();
        $this->sync([['op' => 'set', 'type' => 'prefs', 'data' => self::PREFS]]);

        $this->switchTo('sarah@gr8rstudio.com');

        self::assertEquals(new \stdClass(), $this->rawBootstrap()->prefs);
    }

    /** The Member record owns the name and title the profile shows; old browsers still keep them in their Preferences. */
    public function testANameAndTitleInThePreferencesAreNotStoredAndChangeNoProfile(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => 'prefs', 'data' => [...self::PREFS, 'name' => 'Someone Else', 'title' => 'Intern']]]);

        self::assertSame(['ok' => true], $body);
        $bootstrap = $this->getJson('/api/bootstrap');
        self::assertRecord(self::PREFS, $bootstrap['prefs']);
        self::assertSame(['Tanjim Islam', 'Head of Product'], [$bootstrap['data']['members'][0]['name'], $bootstrap['data']['members'][0]['title']]);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function wrongPreferences(): iterable
    {
        yield 'a list' => [['dark'], 'The Preferences must be an object of settings.'];
        yield 'a nested value' => [[...self::PREFS, 'theme' => ['dark' => true]], 'The Preference "theme" must be text, a number, true, false or null.'];
        yield 'a long text' => [[...self::PREFS, 'lang' => str_repeat('x', 201)], 'The Preference "lang" must be at most 200 characters.'];
        yield 'an unknown theme' => [[...self::PREFS, 'theme' => 'neon'], 'The Preference "theme" must be one of system, light, dark.'];
        yield 'a week starting on day 9' => [[...self::PREFS, 'weekStart' => 9], 'The Preference "weekStart" must be a day from 0 (Sunday) to 6.'];
        yield 'too many settings' => [array_fill_keys(array_map(static fn (int $i): string => 'setting'.$i, range(1, 101)), true), 'The Preferences may hold at most 100 settings.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wrongPreferences')]
    public function testPreferencesOfTheWrongShapeAreRefused(mixed $prefs, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => 'prefs', 'data' => $prefs]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
        self::assertEquals(new \stdClass(), $this->rawBootstrap()->prefs);
    }

    /** viewOf() in src/shell/view-engine.js makes views like this one; a new one has no column widths yet. */
    public function testTheOwnersViewSettingsFollowThemWithEmptyObjectsKeptAsObjects(): void
    {
        $views = [
            'p:p1' => ['filters' => [['f' => 'priority', 'op' => 'is', 'v' => ['urgent']]], 'sort' => ['f' => 'due', 'dir' => -1], 'group' => 'assignee', 'q' => '', 'hidden' => ['start'], 'colW' => ['title' => 320], 'showDone' => false],
            'mytasks' => ['filters' => [], 'sort' => ['f' => 'manual', 'dir' => 1], 'group' => 'status', 'q' => '', 'hidden' => [], 'colW' => [], 'showDone' => true],
        ];
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => 'views', 'data' => $views]]);

        self::assertSame(['ok' => true], $body);
        $this->client->getCookieJar()->clear();
        $this->signIn();
        self::assertRecord($views, $this->getJson('/api/bootstrap')['views']);
        self::assertEquals(new \stdClass(), $this->rawBootstrap()->views->mytasks->colW);
    }

    public function testViewSettingsOfMoreThan64KilobytesAreRefused(): void
    {
        $this->signIn();
        $views = ['p:p1' => ['filters' => [], 'q' => str_repeat('x', 65536)]];

        $body = $this->sync([['op' => 'set', 'type' => 'views', 'data' => $views]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => 'The views setting may take at most 64 KB.'], $body);
    }

    public function testSarahsNotificationSettingsAreHers(): void
    {
        $notifPrefs = ['email_mention' => false, 'email_assign' => true, 'push_due' => false];
        $this->signIn('sarah@gr8rstudio.com');

        $body = $this->sync([['op' => 'set', 'type' => 'notifPrefs', 'data' => $notifPrefs]]);

        self::assertSame(['ok' => true], $body);
        $this->client->getCookieJar()->clear();
        $this->signIn('sarah@gr8rstudio.com');
        self::assertRecord($notifPrefs, $this->getJson('/api/bootstrap')['data']['notifPrefs']);
    }

    public function testRecentSearchesFollowTheMemberNewestFirst(): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => 'recentSearches', 'data' => ['launch plan', 'homepage', 'Sarah']]]);

        self::assertSame(['ok' => true], $body);
        $this->client->getCookieJar()->clear();
        $this->signIn();
        self::assertSame(['launch plan', 'homepage', 'Sarah'], $this->getJson('/api/bootstrap')['data']['recentSearches']);
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function wrongSettings(): iterable
    {
        yield 'views as a list' => ['views', [['filters' => []]], 'The view settings must be an object of views by their key.'];
        yield 'a view that is text' => ['views', ['p:p1' => 'list'], 'The view "p:p1" must be an object.'];
        yield 'column widths as text' => ['views', ['p:p1' => ['colW' => ['title' => 'wide']]], 'The column widths of the view "p:p1" must be numbers.'];
        yield 'a notification setting that is text' => ['notifPrefs', ['email_mention' => 'yes'], 'The notification settings must be at most 100 settings, each true or false.'];
        yield 'recent searches as an object' => ['recentSearches', ['q' => 'homepage'], 'The recent searches must be a list of at most 20 texts of at most 200 characters.'];
        yield 'a recent search that is a number' => ['recentSearches', [42], 'The recent searches must be a list of at most 20 texts of at most 200 characters.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wrongSettings')]
    public function testSettingsOfTheWrongShapeAreRefused(string $type, mixed $data, string $message): void
    {
        $this->signIn();

        $body = $this->sync([['op' => 'set', 'type' => $type, 'data' => $data]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid', 'op' => 0, 'message' => $message], $body);
    }

    private function switchTo(string $email): void
    {
        $this->client->request('POST', '/api/logout');
        $this->signIn($email);
    }

    /** The bootstrap body with JSON objects as objects, so `{}` and `[]` differ. */
    private function rawBootstrap(): \stdClass
    {
        $this->client->request('GET', '/api/bootstrap');

        return json_decode((string) $this->client->getResponse()->getContent(), flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $ops
     *
     * @return array<mixed>
     */
    private function sync(array $ops): array
    {
        return $this->postJson('/api/sync', ['ops' => $ops]);
    }
}
