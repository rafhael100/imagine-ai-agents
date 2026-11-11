<?php
declare(strict_types=1);

use ImagineAI\Domain\{MockError, Run, Step};
use ImagineAI\MockTooling\MockToolCatalog;
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\Mocks\MockSupervisedGateway;

return [
    'U91-01 provider direct transition on another canonical Run poisons suggestion' => function (): void {
        $other = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$other, &$blocked): void {
                try { $other->transition('CONCLUIDA'); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_01');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'FALHOU'
            && $other->state() === 'EXECUTANDO'
            && $provider->calls === 1 && $gw->submitCount() === 0);
        check(array_column($coord->events(), 'code') ===
            ['SYNTHETIC_STARTED', 'SYNTHETIC_STARTED', 'SYNTHETIC_RUN_CHANGED']);
    },
    'U91-02 direct Run::step on another canonical Run is denied sticky' => function (): void {
        $other = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$other, &$blocked): void {
                try { $other->step('SYNTHETIC_U91_OTHER_STEP'); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_02');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $other->state() === 'EXECUTANDO' && $gw->submitCount() === 0);
        check(!in_array('SYNTHETIC_STEP_CREATED',
            array_column($coord->events(), 'code'), true));
    },
    'U91-03 direct new Step on another Run cannot bypass SEC-59 constructor' => function (): void {
        $other = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$other, &$blocked): void {
                try { new Step('SYNTHETIC_U91_NEW_STEP', $other); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_03');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $other->state() === 'EXECUTANDO' && $gw->submitCount() === 0);
    },
    'U91-04 uncaught cross-run transition never silently records success' => function (): void {
        $other = null;
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$other): void {
                $other->transition('CANCELADA');
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_04');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($other->state() === 'EXECUTANDO'
            && $run->state() === 'FALHOU'
            && $gw->submitCount() === 0
            && !in_array('SYNTHETIC_SUGGESTED',
                array_column($coord->events(), 'code'), true));
    },
    'U91-05 wrong scope release token cannot disable other Run guard' => function (): void {
        $other = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$other, &$blocked): void {
                try { $other->endCoordinatorSuggestionScope(new stdClass()); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_05');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $other->state() === 'EXECUTANDO' && $gw->submitCount() === 0);
    },
    'U91-06 nested attempt to replace coordinator token poisons outer suggestion' => function (): void {
        $other = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$other, &$blocked): void {
                try { $other->beginCoordinatorSuggestionScope(new stdClass()); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_06');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $other->state() === 'EXECUTANDO' && $gw->submitCount() === 0);
    },
    'U91-07 read-only inspection of another Run and Step is still permitted' => function (): void {
        $other = null; $otherStep = null; $seen = '';
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$other, &$otherStep, &$seen): void {
                $other->assertActive();
                $seen = $other->state() . ':' . $otherStep->id;
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_07');
        $otherStep = $coord->step($other, 'SYNTHETIC_U91_EXISTING_STEP');
        $out = $tool->dispatch(MockToolCatalog::SUGGEST, MockToolCatalog::VERSION,
            $ctx, $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run);
        check($out['state'] === 'SYNTHETIC_OK'
            && $seen === 'EXECUTANDO:SYNTHETIC_U91_EXISTING_STEP'
            && $other->state() === 'EXECUTANDO'
            && $run->state() === 'EXECUTANDO'
            && $provider->calls === 1 && $gw->submitCount() === 0);
    },
    'U91-08 recovered guard does not prevent legitimate later coordinator operations' => function (): void {
        $other = null; $first = true;
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$other, &$first): void {
                if (!$first) return;
                $first = false;
                try { $other->transition('FALHOU'); }
                catch (MockError) { /* Must taint outer suggestion. */ }
            });
        $other = $coord->start('SYNTHETIC_U91_OTHER_08');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        $validStep = $coord->step($other, 'SYNTHETIC_U91_RECOVERY_STEP');
        $result = $tool->dispatch(MockToolCatalog::SUGGEST, MockToolCatalog::VERSION,
            $ctx, $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $other);
        check($result['state'] === 'SYNTHETIC_OK' && $validStep->run === $other);
        $coord->transition($other, 'CONCLUIDA');
        check($run->state() === 'FALHOU' && $other->state() === 'CONCLUIDA'
            && $provider->calls === 2 && $gw->submitCount() === 0);
    },
    'U91-09 two other Runs stay protected during one suggestion callback' => function (): void {
        $firstOther = null; $secondOther = null; $denials = [];
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$firstOther, &$secondOther, &$denials): void {
                foreach ([$firstOther, $secondOther] as $other) {
                    try { $other->transition('CONCLUIDA'); }
                    catch (MockError $e) { $denials[] = $e->getMessage(); }
                }
            });
        $firstOther = $coord->start('SYNTHETIC_U91_OTHER_09A');
        $secondOther = $coord->start('SYNTHETIC_U91_OTHER_09B');
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($denials === ['PROVIDER_CALLBACK_DENIED', 'PROVIDER_CALLBACK_DENIED']
            && $firstOther->state() === 'EXECUTANDO'
            && $secondOther->state() === 'EXECUTANDO' && $gw->submitCount() === 0);
    },
    'U91-10 direct coordinator call also rejects swallowed cross-run mutation' => function (): void {
        $other = null; $seen = '';
        $provider = new F172MutatingSuggestionProvider(function () use (&$other, &$seen): void {
            try { $other->transition('CONCLUIDA'); }
            catch (MockError $e) { $seen = $e->getMessage(); }
        });
        $gw = new MockSupervisedGateway();
        $coord = new SupervisedCoordinator(context(), $gw, $provider);
        $run = $coord->start('SYNTHETIC_U91_DIRECT_PRIMARY');
        $other = $coord->start('SYNTHETIC_U91_DIRECT_SECONDARY');
        denied('PROVIDER_CALLBACK_DENIED', fn () =>
            $coord->suggest($run, ['text' => 'SYNTHETIC_DRAFT']));
        check($seen === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'EXECUTANDO'
            && $other->state() === 'EXECUTANDO'
            && array_column($coord->events(), 'code') ===
                ['SYNTHETIC_STARTED', 'SYNTHETIC_STARTED']
            && $gw->submitCount() === 0);
    },
];
