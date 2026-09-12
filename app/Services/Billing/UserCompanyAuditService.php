<?php

namespace App\Services\Billing;

use App\User;
use App\UserCompany;
use App\UserCompanyLog;

class UserCompanyAuditService
{
    private const TRACKED = [
        'name',
        'inn',
        'kpp',
        'legal_address',
        'postal_address',
        'email',
        'phone',
    ];

    public function logCreated(UserCompany $company, User $actor): void
    {
        UserCompanyLog::query()->create([
            'user_company_id' => (int) $company->id,
            'user_id' => (int) $actor->id,
            'action' => UserCompanyLog::ACTION_CREATED,
            'company_name' => (string) $company->name,
            'changes' => [
                'snapshot' => $this->snapshot($company),
            ],
        ]);
    }

    public function logUpdated(UserCompany $company, array $before, array $after, User $actor): void
    {
        $diff = [];
        foreach (self::TRACKED as $field) {
            $old = $this->normalize($before[$field] ?? null);
            $new = $this->normalize($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }
            $diff[$field] = ['from' => $old, 'to' => $new];
        }

        if ($diff === []) {
            return;
        }

        UserCompanyLog::query()->create([
            'user_company_id' => (int) $company->id,
            'user_id' => (int) $actor->id,
            'action' => UserCompanyLog::ACTION_UPDATED,
            'company_name' => (string) $company->name,
            'changes' => $diff,
        ]);
    }

    public function logDeleted(UserCompany $company, User $actor): void
    {
        UserCompanyLog::query()->create([
            'user_company_id' => (int) $company->id,
            'user_id' => (int) $actor->id,
            'action' => UserCompanyLog::ACTION_DELETED,
            'company_name' => (string) $company->name,
            'changes' => [
                'snapshot' => $this->snapshot($company),
            ],
        ]);
    }

    private function snapshot(UserCompany $company): array
    {
        $out = [];
        foreach (self::TRACKED as $field) {
            $out[$field] = $this->normalize($company->{$field} ?? null);
        }

        return $out;
    }

    private function normalize($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
