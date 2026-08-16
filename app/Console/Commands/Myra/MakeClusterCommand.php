<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use App\Console\Commands\Myra\Concerns\ScaffoldsNavigation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeClusterCommand extends Command
{
    use ScaffoldsAdmin, ScaffoldsNavigation;

    protected $signature = 'make:myra-cluster {name : Cluster name in PascalCase (e.g. Learning)}
        {--icon=Folder : lucide-vue-next icon name}
        {--group= : Sidebar group i18n key (default navGroups.main)}
        {--slug= : URL/i18n slug (default kebab of name)}
        {--print : Print what would be written instead of touching files}';

    protected $description = 'Scaffold a navigation cluster: one collapsible sidebar entry grouping several resources';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $slug = Str::kebab($this->option('slug') ?: $name);
        $icon = $this->option('icon') ?: 'Folder';
        $group = $this->option('group') ?: 'navGroups.main';
        $print = (bool) $this->option('print');

        $repl = [
            '{{ cluster }}' => $name,
            '{{ slug }}' => $slug,
            '{{ icon }}' => $icon,
            '{{ groupLabelKey }}' => $group,
        ];

        $dest = "app/Admin/Clusters/{$name}Cluster.php";
        $fqcn = "App\\Admin\\Clusters\\{$name}Cluster";

        if ($print) {
            $this->line("Would write: {$dest}");
            $this->line("Would register: \\{$fqcn}::class in config('myra.clusters')");
            $this->line("Would add locale key: clusters.{$slug}.label");

            return self::SUCCESS;
        }

        $this->writeStub('stubs/nav/cluster.stub', $dest, $repl);
        $this->registerCluster($fqcn);
        $this->writeNavLocaleKeys(["clusters.{$slug}.label" => Str::headline($name)]);

        $this->newLine();
        $this->components->info("Cluster '{$name}' scaffolded. Add NavItems to its items() method, then run `npm run build`.");

        return self::SUCCESS;
    }
}
