<?php
/**
 * QiitaApiRepository
 */

namespace App\Infrastructure\Repositories\Api;

use App\Models\Domain\Stock\StockValue;
use App\Models\Domain\Stock\StockValues;
use App\Models\Domain\Stock\StockValueBuilder;

/**
 * Class QiitaApiRepository
 * @package App\Infrastructure\Repositories\Qiita
 */
class QiitaApiRepository extends Repository implements \App\Models\Domain\QiitaApiRepository
{
    /**
     * ストック一覧を取得する
     *
     * @param string $qiitaUserName
     * @param int $page
     * @param int $perPage
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchStock(string $qiitaUserName, int $page, int $perPage): array
    {
        $response = $this->requestToStockApi($qiitaUserName, $page, $perPage);

        $responseArray = json_decode($response->getBody());

        $stockTotalCount = $response->getHeader('total-count');

        $stockValues = [];
        foreach ($responseArray as $stock) {
            $stockValue = $this->buildStockValue($stock);
            array_push($stockValues, $stockValue);
        }

        $stockValues = new StockValues(...$stockValues);

        $response = [
            'stockValues' => $stockValues,
            'totalCount'  => $stockTotalCount[0]
        ];

        return $response;
    }

    /**
     * Stock APIにリクエストを行う
     *
     * @param string $qiitaUserName
     * @param int $page
     * @param int $perPage
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function requestToStockApi(string $qiitaUserName, int $page, int $perPage)
    {
        $uri = sprintf(
            'https://qiita.com/api/v2/users/%s/stocks?page=%d&per_page=%d',
            $qiitaUserName,
            $page,
            $perPage
        );

        return $this->getClient()->request('GET', $uri);
    }

    /**
     * StockValue を作成する
     *
     * @param object $stock
     * @return StockValue
     */
    private function buildStockValue(object $stock): StockValue
    {
        $articleCreatedAt = new \DateTime($stock->created_at);
        $tagNames = $this->buildTagNamesArray($stock->tags);

        $stockValueBuilder = new StockValueBuilder();
        $stockValueBuilder->setArticleId($stock->id);
        $stockValueBuilder->setTitle($stock->title);
        $stockValueBuilder->setUserId($stock->user->id);
        $stockValueBuilder->setProfileImageUrl($stock->user->profile_image_url);
        $stockValueBuilder->setArticleCreatedAt($articleCreatedAt);
        $stockValueBuilder->setTags($tagNames);

        return $stockValueBuilder->build();
    }

    /**
     * タグ名の配列を取得する
     *
     * @param array $tags
     * @return array
     */
    private function buildTagNamesArray(array $tags): array
    {
        $tagNames = [];
        foreach ($tags as $tag) {
            $tagName = $tag->name;
            array_push($tagNames, $tagName);
        }
        return $tagNames;
    }
}
