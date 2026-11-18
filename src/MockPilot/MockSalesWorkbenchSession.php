<?php

declare(strict_types=1);

namespace ImagineAI\MockPilot;

/**
 * P2: completely in-memory commercial workbench, no reader/provider/gateway.
 *
 * The constructor's tenant string is a SYNTHETIC partition label, NOT an
 * authenticated tenant identity. This class is not a policy/entitlement and
 * has no IO, network, persistence, token or service integration.
 */
final class MockSalesWorkbenchSession
{
    public const MAX_DRAFTS = 5;
    public const MAX_RUNS = 8;
    private const TEMPLATES = ['sales', 'analysis'];
    private const LEADS = [
        'A' => ['ref' => 'LEAD_TESTE_ALFA', 'stage' => 'novo'],
        'B' => ['ref' => 'LEAD_TESTE_BETA', 'stage' => 'qualificado'],
        'C' => ['ref' => 'LEAD_TESTE_GAMA', 'stage' => 'proposta'],
    ];
    private const NEXT = [
        'novo' => 'Verificar aderência da oportunidade e elaborar uma abordagem inicial para revisão humana.',
        'qualificado' => 'Preparar um diagnóstico de necessidades e propor uma conversa comercial, sujeita à aprovação.',
        'proposta' => 'Revisar objeções e montar um roteiro de acompanhamento da proposta, sem enviar mensagens.',
    ];

    /** @var list<array{id:string,name:string,template:string}> */
    private array $drafts = [];
    /** @var list<array{id:string,text:string,state:string}> */
    private array $approvals = [];
    private int $runs = 0;
    private int $completedReviews = 0;

    public function __construct(private readonly string $syntheticTenant)
    {
        if (strlen($syntheticTenant) > 90
            || preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D', $syntheticTenant) !== 1) {
            throw new \InvalidArgumentException('LAB_SCOPE_DENIED');
        }
    }

    /** @return ?array{id:string,name:string,template:string} */
    public function createDraft(string $name, string $template): ?array
    {
        if (count($this->drafts) >= self::MAX_DRAFTS
            || !in_array($template, self::TEMPLATES, true)
            || !mb_check_encoding($name, 'UTF-8')) {
            return null;
        }
        $clean = preg_replace('/\s+/u', ' ', trim($name));
        if (!is_string($clean)
            || mb_strlen($clean, 'UTF-8') < 3 || mb_strlen($clean, 'UTF-8') > 40
            || preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} ._-]{2,39}\z/uD', $clean) !== 1) {
            return null;
        }
        foreach ($this->drafts as $existing) {
            if (mb_strtolower($existing['name'], 'UTF-8') === mb_strtolower($clean, 'UTF-8')) {
                return null;
            }
        }
        $draft = [
            'id' => 'SYNTHETIC_AGENT_'.str_pad((string) (count($this->drafts)+1), 2, '0', STR_PAD_LEFT),
            'name' => $clean,
            'template' => $template,
        ];
        $this->drafts[] = $draft;
        return $draft;
    }

    /**
     * @return ?array{
     *   id:string,agent_ref:string,lead_ref:string,stage:string,text:string,
     *   requires_review:bool,approval_ref:?string,used:int
     * }
     */
    public function execute(string $agentRef, string $leadId, string $goal): ?array
    {
        if ($this->runs >= self::MAX_RUNS
            || !in_array($goal, ['next', 'summary'], true)
            || !array_key_exists($leadId, self::LEADS)) {
            return null;
        }

        $template = 'sales';
        if ($agentRef !== 'PRESET_SALES') {
            $matching = null;
            foreach ($this->drafts as $draft) {
                if ($draft['id'] === $agentRef) {
                    $matching = $draft;
                    break;
                }
            }
            if ($matching === null) {
                return null;
            }
            $template = $matching['template'];
        }

        $effectiveGoal = $template === 'analysis' ? 'summary' : $goal;
        $lead = self::LEADS[$leadId];
        $text = $effectiveGoal === 'summary'
            ? 'Estágio atual: '.$lead['stage'].'. Prioridade: acompanhamento supervisionado.'
            : self::NEXT[$lead['stage']];
        $this->runs++;
        $approvalRef = null;
        if ($effectiveGoal === 'next') {
            $approvalRef = 'SYNTHETIC_APPROVAL_'.str_pad((string) $this->runs, 2, '0', STR_PAD_LEFT);
            array_unshift($this->approvals, [
                'id' => $approvalRef,
                'text' => 'Revisar sugestão para '.$lead['ref'],
                'state' => 'PENDENTE',
            ]);
        }
        return [
            'id' => 'SYNTHETIC_RUN_'.str_pad((string) $this->runs, 2, '0', STR_PAD_LEFT),
            'agent_ref' => $agentRef,
            'lead_ref' => $lead['ref'],
            'stage' => $lead['stage'],
            'text' => $text,
            'requires_review' => $effectiveGoal === 'next',
            'approval_ref' => $approvalRef,
            'used' => $this->runs,
        ];
    }

    public function decide(string $approvalRef, string $decision): bool
    {
        if (!in_array($decision, ['APROVADO_EM_ENSAIO', 'REJEITADO_EM_ENSAIO'], true)) {
            return false;
        }
        foreach ($this->approvals as &$item) {
            if ($item['id'] === $approvalRef && $item['state'] === 'PENDENTE') {
                $item['state'] = $decision;
                ++$this->completedReviews;
                unset($item);
                return true;
            }
        }
        unset($item);
        return false;
    }

    /**
     * Only synthetic metadata, never tokens/PII or external command effects.
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        return [
            'contract' => 'SYNTHETIC_AIA_SALES_WORKBENCH_V1',
            'authority' => 'NONE',
            'tenant_ref' => $this->syntheticTenant,
            'drafts' => $this->drafts,
            'run_count' => $this->runs,
            'run_limit' => self::MAX_RUNS,
            'approvals' => $this->approvals,
            'reviewed_count' => $this->completedReviews,
            'external_calls' => 0,
            'persistent_writes' => 0,
            'operational_decision' => 'NO_GO',
        ];
    }
}
