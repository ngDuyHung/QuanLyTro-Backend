<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected const RELATIONS = [];

    protected int $perPage = 15;

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->model
            ->with(self::RELATIONS)
            ->when(isset($filters['search']), fn ($q) =>
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%")
            )
            ->latest()
            ->paginate($filters['per_page'] ?? $this->perPage);
    }

    public function findById(int $id): ?User
    {
        /** @var User|null */
        return $this->model->with(self::RELATIONS)->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        /** @var User */
        return $this->model->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh() ?? $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
