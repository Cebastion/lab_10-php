<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Console\Command;

class GenerateTaskReport extends Command
{
    /**
     * Сигнатура команди.
     *
     * @var string
     */
    protected $signature = 'tasks:report {--project_id= : ID проєкту для фільтрації}';

    /**
     * Опис команди.
     *
     * @var string
     */
    protected $description = 'Виводить звіт по завданнях (можна відфільтрувати за проєктом)';

    public function handle(): int
    {
        $projectId = $this->option('project_id');

        $query = Task::query();

        if ($projectId !== null && $projectId !== '') {
            if (! ctype_digit((string) $projectId)) {
                $this->error('Опція --project_id має бути додатним цілим числом.');

                return self::FAILURE;
            }

            // Окреме повідомлення, якщо такого проєкту взагалі немає
            if (! Project::whereKey($projectId)->exists()) {
                $this->warn("Проєкт з ID {$projectId} не знайдено.");

                return self::SUCCESS;
            }

            $query->where('project_id', $projectId);
        }

        $tasks = $query->orderBy('id')->get(['id', 'title', 'status', 'due_date']);

        if ($tasks->isEmpty()) {
            $this->warn('Завдань не знайдено.');

            return self::SUCCESS;
        }

        $this->info("Знайдено завдань: {$tasks->count()}");

        $this->table(
            ['ID', 'Назва', 'Статус', 'Дедлайн'],
            $tasks->map(fn (Task $task) => [
                $task->id,
                $task->title,
                $task->status,
                $task->due_date ? (string) $task->due_date : '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
