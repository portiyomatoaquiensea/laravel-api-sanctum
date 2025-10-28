<?php

namespace App\Models\Session;

class UserSession
{
    private string $id;
    private string $companyId;
    private string $companyCode;
    private string $companyName;
    private string $customerCode;
    private string $accountId;
    private string $username;
    private string $groupId;
    private string $sboAgent;
    private float $point;
    private string $phoneNumber;
    private string $currency;
    private ?string $joinedBonus; 
    private ?string $bonusType; 
    private ?string $lockBonusDate;
    private ?string $lockBonusDuration;
    private ?string $lockBonusTurnover;
    private ?string $lockBonusMemberAmount;
    private ?string $lockBonusGetBonus;

    public function __construct(
        string $id,
        string $companyId,
        string $companyCode,
        string $companyName,
        string $customerCode,
        string $accountId,
        string $username,
        string $groupId,
        string $sboAgent,
        float $point,
        string $phoneNumber,
        string $currency,
        ?string $joinedBonus = null, // <-- default null
        ?string $bonusType = null, // <-- default null
        ?string $lockBonusDate = null, // <-- default null
        ?string $lockBonusAction = null, // <-- default null
        ?string $lockBonusDuration = null, // <-- default null
        ?string $lockBonusTurnover = null, // <-- default null
        ?string $lockBonusMemberAmount = null, // <-- default null
        ?string $lockBonusGetBonus = null, // <-- default null
    ) {
        $this->id = $id;
        $this->companyId = $companyId;
        $this->companyCode = $companyCode;
        $this->companyName = $companyName;
        $this->customerCode = $customerCode;
        $this->accountId = $accountId;
        $this->username = $username;
        $this->groupId = $groupId;
        $this->sboAgent = $sboAgent;
        $this->point = $point;
        $this->phoneNumber = $phoneNumber;
        $this->currency = $currency;
        $this->joinedBonus = $joinedBonus;
        $this->bonusType = $bonusType;
        $this->lockBonusDate = $lockBonusDate;
        $this->lockBonusAction = $lockBonusAction;
        $this->lockBonusDuration = $lockBonusDuration;
        $this->lockBonusTurnover = $lockBonusTurnover;
        $this->lockBonusMemberAmount = $lockBonusMemberAmount;
        $this->lockBonusGetBonus = $lockBonusGetBonus;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? '',
            $data['company_id'] ?? '',
            $data['company_code'] ?? '',
            $data['company_name'] ?? '',
            $data['customer_code'] ?? '',
            $data['account_id'] ?? '',
            $data['username'] ?? '',
            $data['group_id'] ?? '',
            $data['sbo_agent'] ?? '',
            $data['point'] ?? 0,
            $data['phone_number'] ?? '',
            $data['currency'] ?? '',
            $data['joined_bonus'] ?? null, // <-- map from array
            $data['bonus_type'] ?? null, // <-- map from array
            $data['lock_bonus_date'] ?? null, // <-- map from array
            $data['lock_bonus_action'] ?? null, // <-- map from array
            $data['lock_bonus_duration'] ?? null, // <-- map from array
            $data['lock_bonus_turover'] ?? null, // <-- map from array
            $data['lock_bonus_member_amount'] ?? null, // <-- map from array
            $data['lock_bonus_get_bonus'] ?? null, // <-- map from array
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'company_id'    => $this->companyId,
            'company_code'  => $this->companyCode,
            'company_name'  => $this->companyName,
            'customer_code' => $this->customerCode,
            'account_id'    => $this->accountId,
            'username'      => $this->username,
            'group_id'    => $this->groupId,
            'sbo_agent'    => $this->sboAgent,
            'point'    => $this->point,
            'phone_number'    => $this->phoneNumber,
            'currency'    => $this->currency,
            'joined_bonus'  => $this->joinedBonus, // <-- include in array
            'bonus_type'  => $this->bonusType, // <-- include in array
            'lock_bonus_date'  => $this->lockBonusDate, // <-- include in array
            'lock_bonus_action'  => $this->lockBonusAction, // <-- include in array
            'lock_bonus_duration'  => $this->lockBonusDuration, // <-- include in array
            'lock_bonus_turover'  => $this->lockBonusTurnover, // <-- include in array
            'lock_bonus_member_amount'  => $this->lockBonusMemberAmount, // <-- include in array
            'lock_bonus_get_bonus'  => $this->lockBonusGetBonus, // <-- include in array
        ];
    }
    
    // ✅ Getter methods
    public function getId(): string { return $this->id; }
    public function getCompanyId(): string { return $this->companyId; }
    public function getCompanyCode(): string { return $this->companyCode; }
    public function getCompanyName(): string { return $this->companyName; }
    public function getCustomerCode(): string { return $this->customerCode; }
    public function getAccountId(): string { return $this->accountId; }
    public function getUsername(): string { return $this->username; }
    public function getGroupId(): string { return $this->groupId; }
    public function getSboAgent(): string { return $this->sboAgent; }
    public function getPoint(): float { return $this->point; }
    public function getPhoneNumber(): string { return $this->phoneNumber; }
    public function getCurrency(): string { return $this->currency; }
    public function getJoinedBonus(): ?string { return $this->joinedBonus; } // <-- getter
    public function getBonusType(): ?string { return $this->bonusType; } // <-- getter
    public function getLockBonusDate(): ?string { return $this->lockBonusDate; } // <-- getter
    public function getLockBonusAction(): ?string { return $this->lockBonusAction; } // <-- getter
    public function getLockBonusDuration(): ?string { return $this->lockBonusDuration; } // <-- getter
    public function getLockBonusTurnover(): ?string { return $this->lockBonusTurnover; } // <-- getter
    public function getLockBonusMemberAmount(): ?string { return $this->lockBonusMemberAmount; } // <-- getter
    public function getLockBonusGetBonus(): ?string { return $this->lockBonusGetBonus; } // <-- getter

    public function setJoinedBonus(?string $value): void
    {
        $this->joinedBonus = $value;
    }

    public function setBonusType(?string $value): void
    {
        $this->bonusType = $value;
    }

    public function setLockBonusDate(?string $value): void
    {
        $this->lockBonusDate = $value;
    }

    public function setLockBonusAction(?string $value): void
    {
        $this->lockBonusAction = $value;
    }

    public function setLockBonusDuration(?string $value): void
    {
        $this->lockBonusDuration = $value;
    }

    public function setLockBonusTurnover(?string $value): void
    {
        $this->lockBonusTurnover = $value;
    }

    public function setLockBonusMemberAmount(?string $value): void
    {
        $this->lockBonusMemberAmount = $value;
    }

    public function setLockBonusGetBonus(?string $value): void
    {
        $this->lockBonusGetBonus = $value;
    }

    // $userSession = $sessionService->getUser($request); // get current session

    // if ($userSession) {
    //     // Update the joinedBonus value
    //     $userSession->setJoinedBonus('JOINED');

    //     // Optionally, save it back to session storage if needed
    //     $sessionService->saveUser($request, $userSession);
    // }
}
