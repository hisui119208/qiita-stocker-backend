<?php
/**
 * AccountScenario
 */

namespace App\Services;

use Ramsey\Uuid\Uuid;
use App\Models\Domain\AccountEntity;
use App\Models\Domain\AccountRepository;
use App\Models\Domain\LoginSessionRepository;
use App\Models\Domain\QiitaAccountValueBuilder;
use App\Models\Domain\LoginSessionEntityBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Domain\exceptions\AccountCreatedException;

/**
 * Class AccountScenario
 * @package App\Services
 */
class AccountScenario
{

    /**
     * AccountRepository
     *
     * @var
     */
    private $accountRepository;

    /**
     * LoginSessionRepository
     *
     * @var
     */
    private $loginSessionRepository;
    /**
     * AccountScenario constructor.
     * @param AccountRepository $accountRepository
     * @param LoginSessionRepository $loginSessionRepository
     */
    public function __construct(AccountRepository $accountRepository, LoginSessionRepository $loginSessionRepository)
    {
        $this->accountRepository = $accountRepository;
        $this->loginSessionRepository = $loginSessionRepository;
    }

    /**
     * アカウントを作成する
     *
     * @param array $requestArray
     * @return array
     * @throws AccountCreatedException
     */
    public function create(array $requestArray): array
    {
        $qiitaAccountValueBuilder = new QiitaAccountValueBuilder();
        $qiitaAccountValueBuilder->setAccessToken($requestArray['accessToken']);
        $qiitaAccountValueBuilder->setPermanentId($requestArray['permanentId']);
        $qiitaAccountValue = $qiitaAccountValueBuilder->build();

        if ($qiitaAccountValue->isCreatedAccount($this->accountRepository)) {
            throw new AccountCreatedException(AccountEntity::accountCreatedMessage());
        }

        $accountEntity = $this->accountRepository->create($qiitaAccountValue);

        $sessionId = Uuid::uuid4();

        // TODO 有効期限を適切な期限に修正
        $expiredOn = new \DateTime();
        $expiredOn->add(new \DateInterval('PT1H'));

        $loginSessionEntityBuilder = new LoginSessionEntityBuilder();
        $loginSessionEntityBuilder->setAccountId($accountEntity->getAccountId());
        $loginSessionEntityBuilder->setSessionId($sessionId);
        $loginSessionEntityBuilder->setExpiredOn($expiredOn);
        $loginSessionEntity = $loginSessionEntityBuilder->build();

        $this->accountRepository->saveLoginSession($loginSessionEntity);

        $responseArray = [
            'accountId' => $loginSessionEntity->getAccountId(),
            '_embedded' => ['sessionId' => $loginSessionEntity->getSessionId()]
        ];

        return $responseArray;
    }

    /**
     * アカウントを削除する
     *
     * @param array $params
     */
    public function destroy(array $params)
    {
        try {
            $loginSessionEntity = $this->loginSessionRepository->find($params['sessionId']);

            // TODO 有効期限の検証を行う

            $accountEntity = $loginSessionEntity->findHasAccountEntity($this->accountRepository);
            $accountEntity->cancel();
        } catch (ModelNotFoundException $e) {
            // TODO LoginSessionEntity、AccountEntityが存在しなかった場合のエラー処理を追加する
        }
    }
}
