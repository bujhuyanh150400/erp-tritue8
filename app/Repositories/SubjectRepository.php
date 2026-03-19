<?php


namespace App\Repositories;


use App\Core\Repository\BaseRepository;
use App\Models\Subject;

class SubjectRepository extends BaseRepository
{
    public function getModel(): string
    {
        return Subject::class;
    }

}
