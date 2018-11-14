<?php
/**
 * CategoryEntity
 */

namespace App\Models\Domain\Category;

/**
 * Class CategoryEntity
 * @package App\Models\Domain
 */
class CategoryEntity
{
    /**
     * カテゴリID
     *
     * @var int
     */
    private $Id;

    /**
     * カテゴリ名
     *
     * @var CategoryNameValue
     */
    private $categoryNameValue;

    public function __construct(CategoryEntityBuilder $builder)
    {
        $this->Id = $builder->getId();
        $this->categoryNameValue = $builder->getCategoryNameValue();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->Id;
    }

    /**
     * @return CategoryNameValue
     */
    public function getCategoryNameValue(): CategoryNameValue
    {
        return $this->categoryNameValue;
    }
}