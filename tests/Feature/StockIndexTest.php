<?php
/**
 * StockIndexTest
 */

namespace Tests\Feature;

use App\Eloquents\Stock;
use App\Eloquents\Account;
use App\Eloquents\Category;
use App\Eloquents\StockTag;
use App\Eloquents\AccessToken;
use App\Eloquents\CategoryName;
use App\Eloquents\LoginSession;
use App\Eloquents\QiitaAccount;
use App\Eloquents\QiitaUserName;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class StockIndexTest
 * @package Tests\Feature
 */
class StockIndexTest extends AbstractTestCase
{
    use RefreshDatabase;

    public function setUp()
    {
        parent::setUp();
        $accounts = factory(Account::class)->create();
        $accounts->each(function ($account) {
            factory(QiitaAccount::class)->create(['account_id' => $account->id]);
            factory(QiitaUserName::class)->create(['account_id' => $account->id]);
            factory(AccessToken::class)->create(['account_id' => $account->id]);
            factory(LoginSession::class)->create(['account_id' => $account->id]);
            $categories = factory(Category::class)->create(['account_id' => $account->id]);
            $categories->each(function ($category) {
                factory(CategoryName::class)->create(['category_id' => $category->id]);
            });
            $stocks = factory(Stock::class)->create(['account_id' => $account->id]);
            $stocks->each(function ($stock) {
                factory(StockTag::class)->create(['stock_id' => $stock->id]);
            });
        });
    }

    /**
     * 正常系のテスト
     * ストックの同期ができること
     */
    public function testSuccess()
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 2;

        factory(Account::class)->create();
        factory(QiitaAccount::class)->create(['qiita_account_id' => 2, 'account_id' => $accountId]);
        factory(QiitaUserName::class)->create(['account_id' => $accountId]);
        factory(AccessToken::class)->create(['account_id' => $accountId]);
        factory(LoginSession::class)->create(['account_id' => $accountId, 'id' => $loginSession]);
        factory(Category::class)->create(['account_id' => $accountId]);
        factory(CategoryName::class)->create(['category_id' => 2]);

        $stockCount = 9;
        $stockIdSequence = 2;
        $stockList = $this->createStocks($stockCount, $stockIdSequence);

        for ($i = 0; $i < $stockCount; $i++) {
            factory(Stock::class)->create([
                'account_id'               => $accountId,
                'article_id'               => $stockList[$i]['article_id'],
                'title'                    => $stockList[$i]['title'],
                'user_id'                  => $stockList[$i]['user_id'],
                'profile_image_url'        => $stockList[$i]['profile_image_url'],
                'article_created_at'       => $stockList[$i]['article_created_at_object'],
            ]);

            for ($j = 0; $j < count($stockList[$i]['tags']); $j++) {
                factory(StockTag::class)->create(['stock_id' => $stockIdSequence, 'name' => $stockList[$i]['tags'][$j]]);
            }

            unset($stockList[$i]['article_created_at_object']);
            $stockIdSequence += 1;
        }

        $page = 2;
        $perPage = 2;

        $uri = sprintf(
            '/api/stocks?page=%d&per_page=%d',
            $page,
            $perPage
        );

        $jsonResponse = $this->get(
            $uri,
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        $stockList = array_reverse($stockList);
        $stockList = array_slice($stockList, ($page - 1) * $perPage, $perPage);

        $link = sprintf('<http://127.0.0.1/api/stocks?page=3&per_page=%d>; rel="next", ', $perPage);
        $link .= sprintf('<http://127.0.0.1/api/stocks?page=5&per_page=%d>; rel="last", ', $perPage);
        $link .= sprintf('<http://127.0.0.1/api/stocks?page=1&per_page=%d>; rel="first", ', $perPage);
        $link .= sprintf('<http://127.0.0.1/api/stocks?page=1&per_page=%d>; rel="prev"', $perPage);

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $jsonResponse->assertJson($stockList);
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertHeader('X-Request-Id');
        $jsonResponse->assertHeader('Link', $link);
        $jsonResponse->assertHeader('Total-Count', $stockCount);
    }

    /**
     * ストックのデータを作成する
     *
     * @param int $count
     * @param int $stockIdSequence
     * @return array
     */
    private function createStocks(int $count, int $stockIdSequence) :array
    {
        $stocks = [];
        for ($i = 0; $i < $count; $i++) {
            $secondTag = $i + 1;

            $articleCreatedAtObject = new \DateTime('2018-01-01 00:11:22');
            $articleCreatedAtArray = (array)$articleCreatedAtObject;

            $stock = [
                'id'                        => $stockIdSequence,
                'article_id'                => 'abcdefghij'. str_pad($i, 10, '0'),
                'title'                     => 'title' . $i,
                'user_id'                   => 'user-id-' . $i,
                'profile_image_url'         => 'http://test.com/test-image-updated.jpag'. $i,
                'article_created_at_object' => $articleCreatedAtObject,
                'article_created_at'        => $articleCreatedAtArray['date'],
                'tags'                      => ['tag'. $i, 'tag'. $secondTag]
            ];
            array_push($stocks, $stock);
            $stockIdSequence += 1;
        }

        return $stocks;
    }

    /**
     * 異常系のテスト
     * Authorizationが存在しない場合エラーとなること
     */
    public function testErrorLoginSessionNull()
    {
        $uri = sprintf('/api/stocks?page=%d&per_page=%d', 1, 20);
        $jsonResponse = $this->get($uri);

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 401;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => 'セッションが不正です。再度、ログインしてください。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * 異常系のテスト
     * ログインセッションが不正の場合エラーとなること
     */
    public function testErrorLoginSessionNotFound()
    {
        $loginSession = 'notFound-2bae-4028-b53d-0f128479e650';
        $uri = sprintf('/api/stocks?page=%d&per_page=%d', 1, 20);
        $jsonResponse = $this->get(
            $uri,
            ['Authorization' => 'Bearer '.$loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 401;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => 'セッションが不正です。再度、ログインしてください。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * 異常系のテスト
     * ログインセッションが有効期限切れの場合エラーとなること
     */
    public function testErrorLoginSessionIsExpired()
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        factory(LoginSession::class)->create([
            'id'         => $loginSession,
            'account_id' => 1,
            'expired_on' => '2018-10-01 00:00:00'
        ]);

        $uri = sprintf('/api/stocks?page=%d&per_page=%d', 1, 20);
        $jsonResponse = $this->get(
            $uri,
            ['Authorization' => 'Bearer '.$loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 401;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => 'セッションの期限が切れました。再度、ログインしてください。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }
}