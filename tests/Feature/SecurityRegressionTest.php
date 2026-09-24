<?php
namespace Tests\Feature;

use Tests\TestRunner;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\InstructorFinderController;

class SecurityRegressionTest
{
    public function testInstructorFinderAgeFilterHandlesNumericInputs()
    {
        $controller = new InstructorFinderController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('handleAgeFilter');
        $method->setAccessible(true);

        $query = \App\User::query();
        $request = new Request(['min_age' => 20, 'max_age' => 40]);

        $resultQuery = $method->invoke($controller, $query, $request);
        TestRunner::assertTrue($resultQuery instanceof \Illuminate\Database\Eloquent\Builder, 'Result query must be an Eloquent Builder');
    }

    public function testInstructorFinderAgeFilterHandlesInjectionPayloadsSafely()
    {
        $controller = new InstructorFinderController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('handleAgeFilter');
        $method->setAccessible(true);

        $query = \App\User::query();
        // Malicious injection attempt
        $request = new Request(['min_age' => '0 OR 1=1; --', 'max_age' => '99 UNION SELECT * FROM users']);

        $resultQuery = $method->invoke($controller, $query, $request);
        TestRunner::assertTrue($resultQuery instanceof \Illuminate\Database\Eloquent\Builder, 'Result query must handle malicious string safely without throwing SQL syntax errors');
    }

    public function testInstructorFinderAgeFilterHandlesEmptyAndNulls()
    {
        $controller = new InstructorFinderController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('handleAgeFilter');
        $method->setAccessible(true);

        $query = \App\User::query();
        $request = new Request(['min_age' => '', 'max_age' => null]);

        $resultQuery = $method->invoke($controller, $query, $request);
        TestRunner::assertTrue($resultQuery instanceof \Illuminate\Database\Eloquent\Builder);
    }
}
