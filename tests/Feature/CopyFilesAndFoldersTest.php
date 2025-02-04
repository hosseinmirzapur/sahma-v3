<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Exception;
use Faker\Generator;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyFilesAndFoldersTest extends TestCase
{
    use RefreshDatabase;

    private Generator $faker; /** @phpstan-ignore-line */
    private User $superAdminUser;
    private Collection $files;
    private Collection $folders; /** @phpstan-ignore-line */
    private int $countFilesBeforeOperation;
    private int $countFoldersBeforeOperation;
    private Folder $randomDestinationFolder; /** @phpstan-ignore-line */

  /**
   * @throws BindingResolutionException
   * @throws Exception
   */
    public function setUp(): void
    {
        parent::setUp();
        $this->faker = app()->make(Generator::class);
        $role = Role::factory()->createOne(['title' => 'مدیر سیستم', 'slug' => 'مدیر سیستم']);
        Permission::query()->create(['full' => 1, 'modify' => 0, 'read_only' => 0, 'role_id' => $role->id]);
        $this->superAdminUser = User::factory()->createOne(['is_super_admin' => 1, 'role_id' => $role->id]);
        $departments = Department::factory()->count(random_int(2, 5))->create(['created_by' => $this->superAdminUser]);
      /* @var Department $department*/
        foreach ($departments as $department) {
            DepartmentUser::query()->create([
            'user_id' => $this->superAdminUser->id,
            'department_id' => $department->id
            ]);
        }

        $this->countFilesBeforeOperation = 5;
        $this->countFoldersBeforeOperation = 5;

        $this->files = EntityGroup::factory()
        ->count($this->countFilesBeforeOperation)
        ->create(['user_id' => $this->superAdminUser->id]);

        $this->folders = Folder::factory()
        ->count($this->countFoldersBeforeOperation)
        ->create(['user_id' => $this->superAdminUser->id]);

        $this->assertTrue(EntityGroup::query()->whereNotNull('parent_folder_id')->doesntExist());
        $this->assertTrue(Folder::query()->whereNotNull('parent_folder_id')->doesntExist());

        $this->assertDatabaseCount('entity_groups', $this->countFilesBeforeOperation);
        $this->assertDatabaseCount('folders', $this->countFoldersBeforeOperation);
    }

  /**
   * A basic feature test example.
   * @test
   * @throws Exception
   */
    public function superAdminCanCopyMultipleFilesSuccessfully(): void
    {
        $numberOfCopiedFiles = $this->countFilesBeforeOperation;
        $responsePostCopyFiles = $this->actingAs($this->superAdminUser, 'web')->post(
            route('web.user.dashboard.copy'),
            [
            'folders' => [],
            'files' => $this->files->pluck('id')->toArray(),
            'destinationFolder' => strval($this->randomDestinationFolder->id)
            ]
        );
        /* @var EntityGroup $file */
        foreach ($this->files as $file) {
            $file->refresh();
        }
        $responsePostCopyFiles->assertRedirect();
        $responsePostCopyFiles->assertRedirect(
            route('web.user.dashboard.folder.show', ['folderId' => $this->randomDestinationFolder->getFolderId()])
        );

        $this->assertDatabaseCount('entity_groups', $numberOfCopiedFiles + $this->countFilesBeforeOperation);
        $this->assertDatabaseCount('folders', $this->countFoldersBeforeOperation);
        $this->assertEquals(
            $this->countFilesBeforeOperation,
            EntityGroup::query()->whereNull('parent_folder_id')->count()
        );
        $this->assertEquals(
            $this->countFoldersBeforeOperation,
            Folder::query()->whereNull('parent_folder_id')->count()
        );
        $this->assertEquals(
            $numberOfCopiedFiles,
            EntityGroup::query()->whereNotNull('parent_folder_id')->count()
        );
    }

  /**
   * A basic feature test example.
   * @test
   * @throws Exception
   */
    public function superAdminCanCopyOneFilesSuccessfully(): void
    {
        $numberOfCopiedFiles = 1;
      /* @var EntityGroup $randomFile */
        $randomFile = EntityGroup::findOrFail($this->files->random()->id);/** @phpstan-ignore-line */
        $responsePostCopyFiles = $this->actingAs($this->superAdminUser, 'web')->post(
            route('web.user.dashboard.copy'),
            [
            'folders' => [],
            'files' => [$randomFile->id],
            'destinationFolder' => strval($this->randomDestinationFolder->id)
            ]
        );

        $randomFile->refresh(); /** @phpstan-ignore-line */

        $responsePostCopyFiles->assertRedirect();
        $responsePostCopyFiles->assertRedirect(
            route('web.user.dashboard.folder.show', ['folderId' => $this->randomDestinationFolder->getFolderId()])
        );

        $this->assertDatabaseCount('entity_groups', $numberOfCopiedFiles + $this->countFilesBeforeOperation);
        $this->assertDatabaseCount('folders', $this->countFoldersBeforeOperation);
        $this->assertEquals(
            $this->countFilesBeforeOperation,
            EntityGroup::query()->whereNull('parent_folder_id')->count()
        );
        $this->assertEquals(
            $this->countFoldersBeforeOperation,
            Folder::query()->whereNull('parent_folder_id')->count()
        );
        $this->assertEquals(
            $numberOfCopiedFiles,
            EntityGroup::query()->whereNotNull('parent_folder_id')->count()
        );
    }

//  /**
//   * A basic feature test example.
//   * @test
//   * @throws Exception
//   */
//  public function superAdminCanCopyMultipleFoldersSuccessfully(): void
//  {
//    $numberOfCopiedFolders = $this->countFoldersBeforeOperation - 1;
//    /* @var Folder $randomDestinationFolder*/
//    $randomDestinationFolder = $this->folders->random();
//    $shouldCopyFolders = Folder::query()->where('id', '<>', $randomDestinationFolder->id)->pluck('id')->toArray();
//    $responsePostCopyFiles = $this->actingAs($this->superAdminUser, 'web')->post(
//      route('web.user.dashboard.copy'),
//      [
//        'folders' => $shouldCopyFolders,
//        'files' => [],
//        'destinationFolder' => strval($this->randomDestinationFolder->id)
//      ]
//    );
//
//    /* @var Folder $folder */
//    foreach ($shouldCopyFolders as $folder) {
//      $folder->refresh();
//    }
//    $responsePostCopyFiles->assertRedirect();
//    $responsePostCopyFiles->assertRedirect(
//      route('web.user.dashboard.folder.show', ['folderId' => $this->randomDestinationFolder->getFolderId()])
//    );
//
//    $this->assertDatabaseCount('entity_groups', $this->countFilesBeforeOperation);
//    $this->assertDatabaseCount(
//      'folders',
//      ($numberOfCopiedFolders + $this->countFoldersBeforeOperation)
//    );
//    $this->assertEquals(
//      $this->countFilesBeforeOperation, EntityGroup::query()->count()
//    );
//    $this->assertEquals(
//      $this->countFoldersBeforeOperation, Folder::query()->whereNull('parent_folder_id')->count()
//    );
//    $this->assertEquals(
//      $this->countFoldersBeforeOperation + $numberOfCopiedFolders, Folder::query()->count()
//    );
//  }

//  /**
//   * A basic feature test example.
//   * @test
//   * @throws Exception
//   */
//  public function superAdminCanCopyOneFilesSuccessfully(): void
//  {
//    $this->assertTrue(EntityGroup::query()->whereNotNull('parent_folder_id')->doesntExist());
//    $this->assertDatabaseCount('entity_groups', 5);
//    $countFilesBeforeCopy = EntityGroup::query()->whereNull('parent_folder_id')->count();
//    $this->assertEquals(5, $countFilesBeforeCopy);
//
//    /* @var EntityGroup $randomFile */
//    $randomFile = EntityGroup::find(array_rand($this->files->pluck('id')->toArray()));
//    $responsePostCopyFiles = $this->actingAs($this->superAdminUser, 'web')->post(
//      route('web.user.dashboard.copy'),
//      [
//        'folders' => [],
//        'files' => [$randomFile->id],
//        'destinationFolder' => strval($this->folder->id)
//      ]
//    );
//
//    $randomFile->refresh();
//
//    $responsePostCopyFiles->assertRedirect();
//    $responsePostCopyFiles->assertRedirect(
//      route('web.user.dashboard.folder.show', ['folderId' => $this->folder->getFolderId()])
//    );
//
//    $this->assertDatabaseCount('entity_groups', $countFilesBeforeCopy + 1);
//    $this->assertEquals(5, EntityGroup::query()->whereNull('parent_folder_id')->count());
//    $this->assertEquals(1, EntityGroup::query()->whereNotNull('parent_folder_id')->count());
//  }
}
