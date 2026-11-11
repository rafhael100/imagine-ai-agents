<?php
declare(strict_types=1);

use ImagineAI\Domain\{Step, Run, MockError};
use ImagineAI\MockTooling\MockToolCatalog;
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\Mocks\{MockSupervisedGateway, MockAiSuggestionProvider};

return [
    'U90-01 captured direct Step constructor denies and taints outer suggestion' => function (): void {
        $run = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$run, &$blocked): void {
                try { new Step('SYNTHETIC_U90_HIDDEN_STEP', $run); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'FALHOU' && $gw->submitCount() === 0
            && $provider->calls === 1);
        check(array_column($coord->events(), 'code') ===
            ['SYNTHETIC_STARTED', 'SYNTHETIC_RUN_CHANGED']);
    },
    'U90-02 uncaught direct Step constructor cannot create suggested event' => function (): void {
        $run = null;
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$run): void {
                new Step('SYNTHETIC_U90_UNCAUGHT', $run);
            });
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($run->state() === 'FALHOU'
            && $gw->submitCount() === 0
            && !in_array('SYNTHETIC_SUGGESTED',
                array_column($coord->events(), 'code'), true)
            && !in_array('SYNTHETIC_STEP_CREATED',
                array_column($coord->events(), 'code'), true));
    },
    'U90-03 malformed direct Step is denied by callback before ref parsing' => function (): void {
        $run = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$run, &$blocked): void {
                try { new Step('UNTRUSTED_PRIVATE_STEP', $run); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'FALHOU' && $gw->submitCount() === 0);
    },
    'U90-04 oversized direct Step is rejected before costly ID validation' => function (): void {
        $run = null; $blocked = '';
        [$tool, $grant, $ctx, $run, $coord, $gw] =
            f172SuggestionFixture(function () use (&$run, &$blocked): void {
                try { new Step(str_repeat('X', 1024 * 1024), $run); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'FALHOU'
            && $gw->submitCount() === 0);
    },
    'U90-05 direct coordinator callback denies detached Step without tool' => function (): void {
        $run = null; $blocked = '';
        $provider = new F172MutatingSuggestionProvider(
            function () use (&$run, &$blocked): void {
                try { new Step('SYNTHETIC_U90_DIRECT_COORD', $run); }
                catch (MockError $e) { $blocked = $e->getMessage(); }
            });
        $gw = new MockSupervisedGateway();
        $coord = new SupervisedCoordinator(context(), $gw, $provider);
        $run = $coord->start('SYNTHETIC_U90_DIRECT_RUN');
        denied('PROVIDER_CALLBACK_DENIED', fn () => $coord->suggest(
            $run, ['text' => 'SYNTHETIC_DRAFT']));
        check($blocked === 'PROVIDER_CALLBACK_DENIED'
            && $run->state() === 'EXECUTANDO'
            && array_column($coord->events(), 'code') === ['SYNTHETIC_STARTED']
            && $gw->submitCount() === 0);
    },
    'U90-06 standalone direct Step still works outside callback and not after terminal' => function (): void {
        $run = new Run('SYNTHETIC_U90_STANDALONE', context());
        $run->transition('EXECUTANDO');
        $step = new Step('SYNTHETIC_U90_VALID_STEP', $run);
        check($step->run === $run && $step->context === $run->context);
        denied('INVALID_SYNTHETIC_REF', fn () =>
            new Step('PRIVATE_STEP', $run));
        $run->transition('CONCLUIDA');
        denied('RUN_NOT_ACTIVE', fn () =>
            new Step('SYNTHETIC_U90_TERMINAL_STEP', $run));
    },
    'U90-07 provider may inspect a pre-existing canonical Step read-only' => function (): void {
        $run = null; $step = null; $seen = '';
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$step, &$seen): void {
                $seen = $step->id;
                $step->run->assertActive();
            });
        $step = $coord->step($run, 'SYNTHETIC_U90_PRIOR_STEP');
        $out = $tool->dispatch(MockToolCatalog::SUGGEST, MockToolCatalog::VERSION,
            $ctx, $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run);
        check($out['state'] === 'SYNTHETIC_OK'
            && $seen === 'SYNTHETIC_U90_PRIOR_STEP'
            && $run->state() === 'EXECUTANDO'
            && $provider->calls === 1 && $gw->submitCount() === 0);
        check(array_column($coord->events(), 'code') ===
            ['SYNTHETIC_STARTED', 'SYNTHETIC_STEP_CREATED', 'SYNTHETIC_SUGGESTED']);
    },
    'U90-08 callback guard recovery allows a new valid Run and Step' => function (): void {
        $run = null; $first = true;
        [$tool, $grant, $ctx, $run, $coord, $gw, $provider] =
            f172SuggestionFixture(function () use (&$run, &$first): void {
                if (!$first) return;
                $first = false;
                try { new Step('SYNTHETIC_U90_FAIL_FIRST', $run); }
                catch (MockError) { /* denial must remain sticky */ }
            });
        denied('TOOL_SUGGESTION_DENIED', fn () => $tool->dispatch(
            MockToolCatalog::SUGGEST, MockToolCatalog::VERSION, $ctx,
            $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $run));
        $fresh = $coord->start('SYNTHETIC_U90_FRESH_RUN');
        $valid = $coord->step($fresh, 'SYNTHETIC_U90_FRESH_STEP');
        $out = $tool->dispatch(MockToolCatalog::SUGGEST, MockToolCatalog::VERSION,
            $ctx, $grant, ['text' => 'SYNTHETIC_DRAFT'], 15, $fresh);
        check($out['state'] === 'SYNTHETIC_OK'
            && $run->state() === 'FALHOU' && $fresh->state() === 'EXECUTANDO'
            && $valid->run === $fresh && $provider->calls === 2
            && $gw->submitCount() === 0);
    },
];
