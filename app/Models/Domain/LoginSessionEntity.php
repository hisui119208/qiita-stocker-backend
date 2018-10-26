<?php
/**
 * LoginSessionEntity
 */

namespace App\Models\Domain;

/**
 * Class LoginSessionEntity
 * @package App\Models\Domain
 */
class LoginSessionEntity
{
    /**
     * アカウントID
     *
     * @var string
     */
    private $accountId;

    /**
     * セッションID
     *
     * @var string
     */
    private $sessionId;

    /**
     * 有効期限切れになる日時
     *
     * @var \DateTime
     */
    private $expiredOn;

    /**
     * LoginSessionEntity constructor.
     * @param LoginSessionEntityBuilder $builder
     */
    public function __construct(LoginSessionEntityBuilder $builder)
    {
        $this->accountId = $builder->getAccountId();
        $this->sessionId = $builder->getSessionId();
        $this->expiredOn = $builder->getExpiredOn();
    }

    /**
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return \DateTime
     */
    public function getExpiredOn(): \DateTime
    {
        return $this->expiredOn;
    }

    /**
     * 有効期限が切れているか確認する
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $expiredOn = $this->getExpiredOn();
        $now = new \DateTime();

        if ($expiredOn > $now) {
            return false;
        }
        return true;
    }

    /**
     * ログインセッションが持つアカウントのAccountEntityを取得する
     *
     * @param AccountRepository $accountRepository
     * @return AccountEntity
     */
    public function findHasAccountEntity(AccountRepository $accountRepository): AccountEntity
    {
        return $accountRepository->find($this->getAccountId());
    }
}
