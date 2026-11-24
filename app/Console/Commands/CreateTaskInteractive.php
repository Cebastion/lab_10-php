<?php

namespace App\Console\Commands;

use App\Events\TaskCreated;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateTaskInteractive extends Command
{
    /**
     * Сигнатура команди.
     *
     * @var string
     */
    protected $signature = 'tasks:create-interactive';

    /**
     * Опис команди.
     *
     * @var string
     */
    protected $description = 'Інтерактивне створення завдання через консоль';

    public function handle(): int
    {
        // 1. Діалог з користувачем
        $title       = $this->ask('Назва завдання');
        $description = $this->ask('Опис завдання (Enter — пропустити)');
        $projectId   = $this->ask('ID проєкту');
        $dueDate     = $this->ask('Дедлайн у форматі YYYY-MM-DD (Enter — пропустити)');
        $assignedTo  = $this->ask('ID виконавця (Enter — пропустити)');
        $status      = $this->choice('Статус завдання', ['new', 'in_progress', 'done'], 0);

        $data = [
            'title'       => $title,
            'description' => $description,
            'status'      => $status,
            'project_id'  => $projectId,
            'assigned_to' => $assignedTo,
            'due_date'    => $dueDate,
        ];

        // 2. Валідація (ті самі правила, що й у StoreTaskRequest)
        $validator = Validator::make($data, [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:new,in_progress,done',
            'project_id'  => 'required|integer|exists:projects,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'due_date'    => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            $this->error('Дані не пройшли валідацію:');

            foreach ($validator->errors()->all() as $message) {
                $this->line(" - {$message}");
            }

            return self::FAILURE;
        }

        // Автором завдання робимо власника проєкту (author_id у таблиці обов'язковий)
        $project = Project::findOrFail($projectId);

        // 3. Показуємо підсумок і просимо підтвердження
        $this->table(
            ['Поле', 'Значення'],
            [
                ['Назва', $title],
                ['Опис', $description ?: '—'],
                ['Проєкт', "#{$project->id} {$project->name}"],
                ['Автор (ID)', $project->user_id],
                ['Виконавець (ID)', $assignedTo ?: '—'],
                ['Статус', $status],
                ['Дедлайн', $dueDate ?: '—'],
            ]
        );

        if (! $this->confirm('Зберегти завдання?', true)) {
            $this->warn('Створення завдання скасовано.');

            return self::SUCCESS;
        }

        // 4. Збереження через модель — тут спрацює TaskObserver::created()
        $task = Task::create([
            'title'       => $title,
            'description' => $description ?: null,
            'status'      => $status,
            'project_id'  => $project->id,
            'author_id'   => $project->user_id,
            'assigned_to' => $assignedTo ?: null,
            'due_date'    => $dueDate ?: null,
        ]);

        // Подію TaskCreated Observer сам не викликає (у ЛР9 її запускав контролер),
        // тому з консолі її потрібно викликати вручну — інакше NotifyAssignee не спрацює.
        TaskCreated::dispatch($task);

        $this->info("Завдання успішно створено. ID: {$task->id}");

        return self::SUCCESS;
    }
}
