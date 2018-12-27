<?php
/**
 * CategoryCategorizeTest
 */

namespace Tests\Feature;

use App\Eloquents\Account;
use App\Eloquents\Category;
use App\Eloquents\AccessToken;
use App\Eloquents\CategoryName;
use App\Eloquents\LoginSession;
use App\Eloquents\QiitaAccount;
use App\Eloquents\CategoryStock;
use App\Eloquents\QiitaUserName;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class CategoryCategorizeTest
 * @package Tests\Feature
 */
class CategoryCategorizeTest extends AbstractTestCase
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
                factory(CategoryStock::class)->create(['category_id' => $category->id]);
            });
        });
    }

    /**
     * 正常系のテスト
     * カテゴリとストックのリレーションが作成されること
     */
    public function testSuccess()
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 1;
        factory(LoginSession::class)->create(['id' => $loginSession, 'account_id' => $accountId, ]);

        $categoryId = 1;
        $categorizedArticleId = 'd210ddc2cb1bfeea9331';
        // カテゴリとストックのリレーションが登録済みのデータ
        factory(CategoryStock::class)->create(['category_id' => $categoryId, 'article_id' => $categorizedArticleId]);

        $otherCategoryId = 2;
        $recategorizeArticleId = 'd210ddc2cb1bfeea9332';
        // ストックが他のカテゴリにカテゴライズされているデータ
        factory(Category::class)->create(['account_id' => $accountId]);
        factory(CategoryName::class)->create(['category_id' => $otherCategoryId]);
        factory(CategoryStock::class)->create(['category_id' => $otherCategoryId, 'article_id' => $recategorizeArticleId]);

        $articleIds = [$recategorizeArticleId, $categorizedArticleId,'d210ddc2cb1bfeea9333'];
        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $categoryId,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $jsonResponse->assertStatus(201);
        $jsonResponse->assertHeader('X-Request-Id');

        // DBのテーブルに期待した形でデータが入っているか確認する
        $this->assertDatabaseMissing('categories_stocks', [
            'category_id'       => $otherCategoryId,
            'article_id'        => $recategorizeArticleId,
            'lock_version'      => 0,
        ]);
        $this->assertDatabaseHas('categories_stocks', [
            'category_id'       => $categoryId,
            'article_id'        => $recategorizeArticleId,
            'lock_version'      => 0,
        ]);

        $this->assertDatabaseHas('categories_stocks', [
            'category_id'       => $categoryId,
            'article_id'        => $categorizedArticleId,
            'lock_version'      => 0,
        ]);
        $this->assertDatabaseHas('categories_stocks', [
            'category_id'       => $categoryId,
            'article_id'        => $articleIds[2],
            'lock_version'      => 0,
        ]);
    }

    /**
     * 異常系のテスト
     * カテゴリが見つからない場合エラーとなること
     */
    public function testErrorCategoryIdNotFound()
    {
        $otherAccountId = 2;
        $otherCategoryId = 2;
        $otherCategoryName = 'accountIDが2のカテゴリ';

        factory(Account::class)->create();
        factory(QiitaAccount::class)->create(['qiita_account_id' => 2, 'account_id' => $otherAccountId]);
        factory(QiitaUserName::class)->create(['account_id' => $otherAccountId]);
        factory(AccessToken::class)->create(['account_id' => $otherAccountId]);
        factory(LoginSession::class)->create(['account_id' => $otherAccountId]);
        factory(Category::class)->create(['account_id' => $otherAccountId]);
        factory(CategoryName::class)->create(['category_id' => $otherCategoryId, 'name' => $otherCategoryName]);

        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 1;
        factory(LoginSession::class)->create(['id' => $loginSession, 'account_id' => $accountId, ]);

        $articleIds = ['d210ddc2cb1bfeea9331','d210ddc2cb1bfeea9332','d210ddc2cb1bfeea9333'];
        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $otherCategoryId,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 404;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => '不正なリクエストが行われました。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * 異常系のテスト
     * Authorizationが存在しない場合エラーとなること
     */
    public function testErrorLoginSessionNull()
    {
        $categoryId = 1;
        $articleIds = ['d210ddc2cb1bfeea9331','d210ddc2cb1bfeea9332','d210ddc2cb1bfeea9333'];
        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $categoryId,
                'articleIds' => $articleIds
            ]
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
     * ログインセッションが不正の場合エラーとなること
     */
    public function testErrorLoginSessionNotFound()
    {
        $loginSession = 'notFound-2bae-4028-b53d-0f128479e650';
        $categoryId = 1;
        $articleIds = ['d210ddc2cb1bfeea9331','d210ddc2cb1bfeea9332','d210ddc2cb1bfeea9333'];

        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $categoryId,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
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
        $categoryId = 1;
        $articleIds = ['d210ddc2cb1bfeea9331','d210ddc2cb1bfeea9332','d210ddc2cb1bfeea9333'];
        factory(LoginSession::class)->create([
            'id'         => $loginSession,
            'account_id' => 1,
            'expired_on' => '2018-10-01 00:00:00'
        ]);

        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $categoryId,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 401;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => 'セッションの期限が切れました。再度、ログインしてください。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * 異常系のテスト
     * カテゴリIDのバリデーション
     *
     * @param $categoryId
     * @dataProvider categoryIdProvider
     */
    public function testErrorCategoryIdValidation($categoryId)
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 1;
        factory(LoginSession::class)->create(['id' => $loginSession, 'account_id' => $accountId, ]);

        $articleIds = ['d210ddc2cb1bfeea9331','d210ddc2cb1bfeea9332','d210ddc2cb1bfeea9333'];
        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => $categoryId,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 422;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => '不正なリクエストが行われました。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * カテゴリIDのデータプロバイダ
     *
     * @return array
     */
    public function categoryIdProvider()
    {
        return [
            'emptyString'        => [''],
            'null'               => [null],
            'emptyArray'         => [[]],
            'string'             => ['a'],
            'symbol'             => ['1@'],
            'multiByte'          => ['１'],
            'negativeNumber'     => [-1],
            'double'             => [1.1],
            'lessThanMin'        => [0],
            'greaterThanMax'     => [18446744073709551615],
        ];
    }

    /**
     * 異常系のテスト
     * ArticleIDのバリデーション
     *
     * @param $articleId
     * @dataProvider articleIdProvider
     */
    public function testErrorArticleIdValidation($articleId)
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 1;
        factory(LoginSession::class)->create(['id' => $loginSession, 'account_id' => $accountId, ]);

        $articleIds = ['d210ddc2cb1bfeea9331'];
        array_push($articleIds, $articleId);

        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => 1,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 422;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => '不正なリクエストが行われました。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * ArticleIDのデータプロバイダ
     *
     * @return array
     */
    public function articleIdProvider()
    {
        return [
            'emptyString'             => [''],
            'null'                    => [null],
            'emptyArray'              => [[]],
            'symbol'                  => ['a210ddc2cb1bfeea933@'],
            'multiByte'               => ['１１１１１１１１１１１１１１１１１１１１'],
            'tooShortLength'          => ['a210ddc2cb1bfeea933'],
            'tooLongLength'           => ['a210ddc2cb1bfeea93311'],
            'f-z'                     => ['gz10ddc2cb1bfeea9331']
        ];
    }

    /**
     * 異常系のテスト
     * ArticleIdsのバリデーション
     *
     * @param $articleIds
     * @dataProvider articleIdsProvider
     */
    public function testErrorArticleIdsValidation($articleIds)
    {
        $loginSession = '54518910-2bae-4028-b53d-0f128479e650';
        $accountId = 1;
        factory(LoginSession::class)->create(['id' => $loginSession, 'account_id' => $accountId, ]);

        $jsonResponse = $this->postJson(
            '/api/categories/stocks',
            [
                'id'         => 1,
                'articleIds' => $articleIds
            ],
            ['Authorization' => 'Bearer ' . $loginSession]
        );

        // 実際にJSONResponseに期待したデータが含まれているか確認する
        $expectedErrorCode = 422;
        $jsonResponse->assertJson(['code' => $expectedErrorCode]);
        $jsonResponse->assertJson(['message' => '不正なリクエストが行われました。']);
        $jsonResponse->assertStatus($expectedErrorCode);
        $jsonResponse->assertHeader('X-Request-Id');
    }

    /**
     * ArticleIDのデータプロバイダ
     *
     * @return array
     */
    public function articleIdsProvider()
    {
        return [
            'emptyArray'                   => [[]],
            'emptyString'                  => [''],
            'notArray'                     => ['a210ddc2cb1bfeea9331'],
            'tooLongLengthArray'           => [array_fill(0, 21, 'a210ddc2cb1bfeea9332')]
        ];
    }
}
