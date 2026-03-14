<?php

namespace App\Core\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct()
    {
        $this->setModel();
    }

    /**
     * Define the model class specific for this repository
     * @return string
     */
    abstract public function getModel();

    /**
     * Instantiate the model
     */
    public function setModel()
    {
        $this->model = app()->make($this->getModel());
    }

    public function query()
    {
        return $this->model->query();
    }

    public function getAll(array $columns = ['*'], array $relations = []): \Illuminate\Support\Collection
    {
        return $this->model->with($relations)->get($columns);
    }


    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }
    public function findById(int $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->select($columns)->find($id);
    }

    public function findByCondition(array $conditions, array $relations = []): ?Model
    {
        return $this->model->with($relations)->where($conditions)->first();
    }

    /**
     * Trả về Query Builder nếu cần custom query phức tạp ở Child Repositories
     */
    protected function getQuery(): Builder
    {
        return $this->model->newQuery();
    }

    public function create(array $attributes)
    {
        return $this->model->create($attributes);
    }

    public function updateById($id, array $attributes)
    {
        $record = $this->find($id);
        if ($record) {
            $record->update($attributes);
            return $record;
        }
        return false;
    }

    public function deleteById($id)
    {
        $record = $this->find($id);
        if ($record) {
            $record->delete();
            return true;
        }
        return false;
    }
}
