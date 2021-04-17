<?php

namespace Gametech\Core\Eloquent;

use Illuminate\Container\Container as Application;

use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Repository\Traits\CacheableRepository;


abstract class Repository extends BaseRepository implements CacheableInterface
{

    use CacheableRepository;

    protected $app;

    /**
     * @var Model
     */
    protected $model;

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Find data by field and value
     *
     * @param string $field
     * @param string $value
     * @param array $columns
     * @return mixed
     */
    public function findOneByField(string $field, $value = null, $columns = ['*'])
    {
        $model = $this->findByField($field, $value, $columns);

        return $model->first();
    }

    /**
     * Find data by field and value
     *
     * @param array $where
     * @param array $columns
     * @return mixed
     */
    public function findOneWhere(array $where, $columns = ['*'])
    {
        $model = $this->findWhere($where, $columns);

        return $model->first();
    }


    /**
     * Find data by id
     *
     * @param       $id
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        return $this->find($id, $columns);
    }

    public function find($id, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $this->parserResult($model);
    }


    /**
     * @throws RepositoryException
     */
    public function sum(string $columns)
    {
        $this->applyCriteria();
        $this->applyScope();

        $sum = $this->model->sum($columns);

        $this->resetModel();
        $this->resetScope();

        return $sum;
    }

    /**
     * @throws RepositoryException
     */
    public function avg(string $columns)
    {
        $this->applyCriteria();
        $this->applyScope();

        $avg = $this->model->avg($columns);

        $this->resetModel();
        $this->resetScope();

        return $avg;
    }


}
