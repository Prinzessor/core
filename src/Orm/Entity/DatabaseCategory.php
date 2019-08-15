<?php

declare(strict_types=1);

namespace Stu\Orm\Entity;

use Stu\Orm\Repository\DatabaseEntryRepositoryInterface;

/**
 * @Entity
 * @Table(name="stu_database_categories")
 * @Entity(repositoryClass="Stu\Orm\Repository\DatabaseCategoryRepository")
 **/
final class DatabaseCategory implements DatabaseCategoryInterface
{
    /** @Id @Column(type="integer") @GeneratedValue * */
    private $id;

    /** @Column(type="string") * */
    private $description;

    /** @Column(type="integer") * */
    private $points;

    /** @Column(type="integer") * */
    private $type;

    /** @Column(type="integer") * */
    private $sort;

    public function getId(): int
    {
        return $this->id;
    }

    public function setDescription(string $description): DatabaseCategoryInterface
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setPoints(int $points): DatabaseCategoryInterface
    {
        $this->points = $points;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setType(int $type): DatabaseCategoryInterface
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setSort(int $sort): DatabaseCategoryInterface
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getEntries(): array
    {
        // @todo refactor
        global $container;
        return $container->get(DatabaseEntryRepositoryInterface::class)->getByCategoryId($this->getId());
    }

    public function isCategoryStarSystems(): bool
    {
        return $this->getId() == DATABASE_CATEGORY_STARSYSTEMS;
    }

    public function isCategoryTradePosts(): bool
    {
        return $this->getId() == DATABASE_CATEGORY_TRADEPOSTS;
    }

    public function displayDefaultList(): bool
    {
        return !$this->isCategoryStarSystems() && !$this->isCategoryTradePosts();
    }
}