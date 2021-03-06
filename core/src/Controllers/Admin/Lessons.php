<?php

namespace App\Controllers\Admin;

use App\Model\File;
use App\Model\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface;
use Vesp\Controllers\ModelController;

class Lessons extends ModelController
{
    protected $model = Lesson::class;
    protected $scope = 'lessons';

    public function post(): ResponseInterface
    {
        if (!$action = $this->getProperty('action')) {
            return $this->failure('Вы должны указать action');
        }
        if (!$id = (int)$this->getProperty('id')) {
            return $this->failure('Вы должны указать id объекта');
        }
        /** @var Lesson $object */
        if (!$object = Lesson::query()->find($id)) {
            return $this->failure('Не могу загрузить объект');
        }

        switch ($action) {
            case 'move_up':
                if ($object->rank > 0) {
                    $object->rank -= 1;
                    /** @var Lesson $other */
                    if (
                        $other = $object->course->lessons()->where([
                        'rank' => $object->rank,
                        'section' => $object->section,
                        ])->first()
                    ) {
                        $other->rank += 1;
                        $other->save();
                    }
                    $object->save();
                }
                break;
            case 'move_down':
                $object->rank += 1;
                /** @var Lesson $other */
                if (
                    $other = $object->course->lessons()->where([
                    'rank' => $object->rank,
                    'section' => $object->section,
                    ])->first()
                ) {
                    $other->rank -= 1;
                    $other->save();
                }
                $object->save();
                break;
            default:
                return $this->failure('Указан неверный action');
        }

        $section = $rank = 0;
        $records = $object->course->lessons()
            ->orderBy('section', 'asc')
            ->orderBy('rank', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
        /** @var Lesson $record */
        foreach ($records as $record) {
            if ($record->section > $section) {
                $rank = 0;
                $section = $record->section;
            }
            $record->rank = $rank;
            $record->save();
            $rank++;
        }

        return $this->success();
    }

    /**
     * @param Builder $c
     * @return Builder|mixed
     */
    protected function beforeGet(Builder $c): Builder
    {
        $c->with('file:id,title,updated_at');
        $c->with('course:id,template_id', 'course.template');

        return $c;
    }

    /**
     * @param Lesson $record
     *
     * @return bool|string
     */
    public function beforeSave($record)
    {
        if ($record->section == 0) {
            if (
                Lesson::query()->where(['section' => 0, 'course_id' => $record->course_id])->where(
                    'id',
                    '!=',
                    $record->id
                )->count()
            ) {
                return 'У вас уже есть бонусное видео в этом курсе. Такое видео может быть только одно.';
            }
        }

        if ($archive = $this->getProperty('new_file', $this->getProperty('file'))) {
            if (is_array($archive) && !empty($archive['file'])) {
                if (!$file = $record->file) {
                    $file = new File();
                }

                if ($file->uploadFile($archive['file'], $archive['metadata'])) {
                    $record->file_id = $file->id;
                }
            }
        }

        return parent::beforeSave($record);
    }

    /**
     * @param Builder $c
     *
     * @return mixed
     */
    protected function beforeCount(Builder $c): Builder
    {
        if ($course_id = (int)$this->getProperty('course_id')) {
            $c->where(['course_id' => $course_id]);
        }
        if ($query = trim($this->getProperty('query'))) {
            $c->where(static function (Builder $c) use ($query) {
                $c->where('title', 'LIKE', "%$query%");
                $c->orWhere('description', 'LIKE', "%$query%");
            });
        }
        $section = $this->getProperty('section');
        if ($section !== null) {
            $c->where(['section' => (int)$section]);
        }

        return $c;
    }

    /**
     * @param Builder $c
     * @return Builder
     */
    protected function afterCount(Builder $c): Builder
    {
        $c->with('video:id,preview');
        $c->orderBy('section', 'asc');
        $c->orderBy('rank', 'asc');

        return $c;
    }
}
