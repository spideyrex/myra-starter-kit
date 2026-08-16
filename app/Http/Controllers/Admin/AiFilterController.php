<?php

namespace App\Http\Controllers\Admin;

use App\Admin\QueryBuilder\FilterScopes;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRunner;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiService;
use App\Services\Ai\Compiler\DashboardSummariser;
use App\Services\Ai\Compiler\RuleTreeCompiler;
use App\Services\Ai\Compiler\SchemaDraftCompiler;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AiFilterController extends Controller
{
    /** Natural language in, a validated rule tree out. Never SQL. */
    public function compile(Request $request, RuleTreeCompiler $compiler): JsonResponse
    {
        Gate::authorize('ai.filter');
        abort_unless(config('myra.ai.features.filter') === true, 404);
        abort_unless(app(AiService::class)->isEnabled(), 404);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:'.RuleTreeCompiler::MAX_PROMPT],
            'scope' => ['required', 'string', Rule::in(array_keys(FilterScopes::map()))],
        ]);

        $set = FilterScopes::resolve($data['scope']);   // controller-side literal
        $tree = $compiler->compile($data['prompt'], $set, $request->user());

        // `applied:false` is the contract. Applying goes back through the normal
        // filter route, where RuleTree::parse() runs AGAIN. Re-validation is not
        // redundant: the server minted this, but the client is the transport, and
        // the transport has no authority.
        return response()->json(['tree' => $tree->toArray(), 'applied' => false]);
    }

    public function schema(Request $request, SchemaDraftCompiler $compiler): JsonResponse
    {
        Gate::authorize('ai.schema');
        abort_unless(config('myra.ai.features.schema') === true, 404);
        abort_unless(app(AiService::class)->isEnabled(), 404);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:'.RuleTreeCompiler::MAX_PROMPT],
        ]);

        return response()->json(['draft' => $compiler->draft($data['prompt'], $request->user())]);
    }

    /**
     * Summarises the acting user's own dashboard. The batch is re-run here from
     * the request SPEC — client-supplied numbers are never accepted.
     */
    public function summarise(Request $request, DashboardSummariser $summariser): JsonResponse
    {
        Gate::authorize('ai.summarise');
        abort_unless(config('myra.ai.features.summarise') === true, 404);
        abort_unless(app(AiService::class)->isEnabled(), 404);

        $data = $request->validate([
            'widgets' => ['required', 'array', 'max:'.DashboardSummariser::MAX_WIDGETS],
            'locale' => ['nullable', 'string', 'in:en,ms,zh'],
        ]);

        $results = $this->runOwnBatch((array) $data['widgets'], $request);

        return response()->json([
            'summary' => $summariser->summarise($results, $data['locale'] ?? app()->getLocale(), $request->user()),
        ]);
    }

    /**
     * @return array<string,\App\Admin\Report\ReportResult>
     */
    private function runOwnBatch(array $specs, Request $request): array
    {
        $user = $request->user();
        $out = [];

        foreach ($specs as $slot => $spec) {
            $spec = is_array($spec) ? $spec : [];
            $key = (string) ($spec['report'] ?? $slot);

            if (! ReportRegistry::has($key)) {
                continue;
            }

            $definition = ReportRegistry::resolve($key);

            if (! ($user instanceof Authorizable && $user->can($definition->permissionAbility()))) {
                continue;
            }

            $out[(string) $slot] = (new ReportRunner($definition))
                ->run(ReportRequest::parse($spec, $definition, $user), $user);
        }

        return $out;
    }
}
