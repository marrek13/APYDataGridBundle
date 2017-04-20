<?php

namespace APY\DataGridBundle\Grid\Tests;

use APY\DataGridBundle\Grid\Action\MassAction;
use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\Column;
use APY\DataGridBundle\Grid\Columns;
use APY\DataGridBundle\Grid\Export\ExportInterface;
use APY\DataGridBundle\Grid\Grid;
use APY\DataGridBundle\Grid\GridConfigInterface;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Rows;
use APY\DataGridBundle\Grid\Source\Entity;
use APY\DataGridBundle\Grid\Source\Source;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GridTest extends TestCase
{
    /**
     * @var Grid
     */
    private $grid;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $router;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $authChecker;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $request;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $session;

    /**
     * @var string
     */
    private $gridId;

    /**
     * @var string
     */
    private $gridHash;

    public function testInitializeWithoutAnyConfiguration()
    {
        $this->arrange();

        $column = $this->createMock(Column::class);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $this->assertAttributeEquals(false, 'persistence', $this->grid);
        $this->assertAttributeEmpty('routeParameters', $this->grid);
        $this->assertAttributeEmpty('routeUrl', $this->grid);
        $this->assertAttributeEmpty('source', $this->grid);
        $this->assertAttributeEmpty('defaultOrder', $this->grid);
        $this->assertAttributeEmpty('limits', $this->grid);
        $this->assertAttributeEmpty('maxResults', $this->grid);
        $this->assertAttributeEmpty('page', $this->grid);

        $this->router->expects($this->never())->method($this->anything());
        $column->expects($this->never())->method($this->anything());
    }

    public function testInitializePersistence()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isPersisted')->willReturn(true);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals(true, 'persistence', $this->grid);
    }

    public function testInitializeRouteParams()
    {
        $routeParams = ['foo' => 1, 'bar' => 2];

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRouteParameters')->willReturn($routeParams);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($routeParams, 'routeParameters', $this->grid);
    }

    public function testInitializeRouteUrlWithoutParams()
    {
        $route = 'vendor.bundle.controller.route_name';
        $routeParams = ['foo' => 1, 'bar' => 2];
        $url = 'aRandomUrl';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRouteParameters')->willReturn($routeParams);
        $gridConfig->method('getRoute')->willReturn($route);

        $this->arrange($gridConfig);
        $this->router->method('generate')->with($route, $routeParams)->willReturn($url);

        $this->grid->initialize();

        $this->assertAttributeEquals($url, 'routeUrl', $this->grid);
    }

    public function testInitializeRouteUrlWithParams()
    {
        $route = 'vendor.bundle.controller.route_name';
        $url = 'aRandomUrl';
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRoute')->willReturn($route);

        $this->arrange($gridConfig);
        $this->router->method('generate')->with($route, null)->willReturn($url);

        $this->grid->initialize();

        $this->assertAttributeEquals($url, 'routeUrl', $this->grid);
    }

    public function testInizializeColumnsNotFilterableAsGridIsNotFilterable()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isFilterable')->willReturn(false);

        $column = $this->createMock(Column::class);

        $this->arrange($gridConfig);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $column->expects($this->any())->method('setFilterable')->with(false);
    }

    public function testInizializeColumnsNotSortableAsGridIsNotSortable()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isSortable')->willReturn(false);

        $column = $this->createMock(Column::class);

        $this->arrange($gridConfig);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $column->expects($this->any())->method('setSortable')->with(false);
    }

    public function testInitializeNotEntitySource()
    {
        $source = $this->createMock(Source::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
    }

    public function testInitializeEntitySourceWithoutGroupByFunction()
    {
        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->never())->method('setGroupBy');
    }

    public function testInitializeEntitySourceWithoutGroupByScalarValue()
    {
        $groupByField = 'groupBy';

        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);
        $gridConfig->method('getGroupBy')->willReturn($groupByField);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->any())->method('setGroupBy')->with([$groupByField]);
    }

    public function testInitializeEntitySourceWithoutGroupByArrayValues()
    {
        $groupByArray = ['groupByFoo', 'groupByBar'];

        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);
        $gridConfig->method('getGroupBy')->willReturn($groupByArray);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->any())->method('setGroupBy')->with($groupByArray);
    }

    public function testInizializeDefaultOrder()
    {
        $sortBy = 'SORTBY';
        $orderBy = 'ORDERBY';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSortBy')->willReturn($sortBy);
        $gridConfig->method('getOrder')->willReturn($orderBy);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals(sprintf('%s|%s', $sortBy, strtolower($orderBy)), 'defaultOrder', $this->grid);
    }

    public function testInizializeDefaultOrderWithoutOrder()
    {
        $sortBy = 'SORTBY';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSortBy')->willReturn($sortBy);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        // @todo: is this an admitted case?
        $this->assertAttributeEquals("$sortBy|", 'defaultOrder', $this->grid);
    }

    public function testInizializeLimits()
    {
        $maxPerPage = 10;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getMaxPerPage')->willReturn($maxPerPage);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals([$maxPerPage => (string) $maxPerPage], 'limits', $this->grid);
    }

    public function testInizializeMaxResults()
    {
        $maxResults = 50;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getMaxResults')->willReturn($maxResults);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($maxResults, 'maxResults', $this->grid);
    }

    public function testInizializePage()
    {
        $page = 1;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getPage')->willReturn($page);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($page, 'page', $this->grid);
    }

    public function testSetSourceOneThanOneTime()
    {
        $source = $this->createMock(Source::class);

        // @todo maybe this exception should not be \InvalidArgumentException?
        $this->expectException(\InvalidArgumentException::class);

        $this->grid->setSource($source);
        $this->grid->setSource($source);
    }

    public function testSetSource()
    {
        $source = $this->createMock(Source::class);
        $source->expects($this->once())->method('initialise')->with($this->container);
        $source->expects($this->once())->method('getColumns')->with($this->isInstanceOf(Columns::class));

        $this->grid->setSource($source);

        $this->assertAttributeEquals($source, 'source', $this->grid);
    }

    public function testGetSource()
    {
        $source = $this->createMock(Source::class);

        $this->grid->setSource($source);

        $this->assertEquals($source, $this->grid->getSource());
    }

    public function testGetNullHashIfNotCreated()
    {
        $this->assertNull($this->grid->getHash());
    }

    public function testHandleRequestRaiseExceptionIfSourceNotSetted()
    {
        $this->expectException(\LogicException::class);

        $this->grid->handleRequest($this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock());
    }

    public function testAddColumnToLazyColumnsWithoutPosition()
    {
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $this->grid->addColumn($column);

        $this->assertAttributeEquals([['column' => $column, 'position' => 0]], 'lazyAddColumn', $this->grid);
    }

    public function testAddColumnToLazyColumnsWithPosition()
    {
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $this->grid->addColumn($column, 1);

        $this->assertAttributeEquals([['column' => $column, 'position' => 1]], 'lazyAddColumn', $this->grid);
    }

    public function testAddColumnsToLazyColumnsWithSamePosition()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();

        $this->grid->addColumn($column1, 1);
        $this->grid->addColumn($column2, 1);

        $this->assertAttributeEquals([
            ['column' => $column1, 'position' => 1],
            ['column' => $column2, 'position' => 1], ],
            'lazyAddColumn',
            $this->grid
        );
    }

    public function testGetColumnFromLazyColumns()
    {
        $columnId = 'foo';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('getId')->willReturn($columnId);
        $this->grid->addColumn($column);

        $this->assertEquals($column, $this->grid->getColumn($columnId));
    }

    public function testGetColumnFromColumns()
    {
        $columnId = 'foo';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $columns = $this->createMock(Columns::class);
        $columns->expects($this->once())->method('getColumnById')->with($columnId)->willReturn($column);

        $this->grid->setColumns($columns);

        $this->assertEquals($column, $this->grid->getColumn($columnId));
    }

    public function testRaiseExceptionIfGetNonExistentColumn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->grid->getColumn('foo');
    }

    public function testGetColumns()
    {
        $this->assertInstanceOf(Columns::class, $this->grid->getColumns());
    }

    public function testHasColumnInLazyColumns()
    {
        $columnId = 'foo';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('getId')->willReturn($columnId);
        $this->grid->addColumn($column);

        $this->assertTrue($this->grid->hasColumn($columnId));
    }

    public function testHasColumnInColumns()
    {
        $columnId = 'foo';
        $columns = $this->createMock(Columns::class);
        $columns->expects($this->once())->method('hasColumnById')->with($columnId)->willReturn(true);

        $this->grid->setColumns($columns);

        $this->assertTrue($this->grid->hasColumn($columnId));
    }

    public function testSetColumns()
    {
        $columns = $this->createMock(Columns::class);
        $this->grid->setColumns($columns);

        $this->assertAttributeEquals($columns, 'columns', $this->grid);
    }

    public function testColumnsReorderAndKeepOtherColumns()
    {
        $ids = ['col1', 'col3', 'col2'];
        $columns = $this->createMock(Columns::class);
        $columns->expects($this->once())->method('setColumnsOrder')->with($ids, true);
        $this->grid->setColumns($columns);

        $this->grid->setColumnsOrder($ids, true);
    }

    public function testColumnsReorderAndDontKeepOtherColumns()
    {
        $ids = ['col1', 'col3', 'col2'];
        $columns = $this->createMock(Columns::class);
        $columns->expects($this->once())->method('setColumnsOrder')->with($ids, false);
        $this->grid->setColumns($columns);

        $this->grid->setColumnsOrder($ids, false);
    }

    public function testAddMassActionWithoutRole()
    {
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getRole')->willReturn(null);

        $this->grid->addMassAction($massAction);

        $this->assertAttributeEquals([$massAction], 'massActions', $this->grid);
    }

    public function testAddMassActionWithGrantForActionRole()
    {
        $role = 'aRole';
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getRole')->willReturn($role);
        $this->authChecker->method('isGranted')->with($role)->willReturn(true);

        $this->grid->addMassAction($massAction);

        $this->assertAttributeEquals([$massAction], 'massActions', $this->grid);
    }

    public function testAddMassActionWithoutGrantForActionRole()
    {
        $role = 'aRole';
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getRole')->willReturn($role);
        $this->authChecker->method('isGranted')->with($role)->willReturn(false);

        $this->grid->addMassAction($massAction);

        $this->assertAttributeEmpty('massActions', $this->grid);
    }

    public function testGetMassActions()
    {
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getRole')->willReturn(null);

        $this->grid->addMassAction($massAction);

        $this->assertEquals([$massAction], $this->grid->getMassActions());
    }

    public function testRaiseExceptionIfAddTweakWithNotValidId()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->grid->addTweak('title', [], '#tweakNotValidId');
    }

    public function testAddTweakWithId()
    {
        $title = 'aTweak';
        $tweak = ['filters' => [], 'order' => 'columnId', 'page' => 1, 'limit' => 50, 'export' => 1, 'massAction' => 1];
        $id = 'aValidTweakId';
        $group = 'tweakGroup';

        $this->grid->addTweak($title, $tweak, $id, $group);

        $result = [$id => array_merge(['title' => $title, 'id' => $id, 'group' => $group], $tweak)];

        $this->assertAttributeEquals($result, 'tweaks', $this->grid);
    }

    public function testAddTweakWithoutId()
    {
        $title = 'aTweak';
        $tweak = ['filters' => [], 'order' => 'columnId', 'page' => 1, 'limit' => 50, 'export' => 1, 'massAction' => 1];
        $group = 'tweakGroup';

        $this->grid->addTweak($title, $tweak, null, $group);

        $result = [0 => array_merge(['title' => $title, 'id' => null, 'group' => $group], $tweak)];

        $this->assertAttributeEquals($result, 'tweaks', $this->grid);
    }

    public function testAddRowActionWithoutRole()
    {
        $colId = 'aColId';
        // @todo: It seems that RowActionInterface does not have getRole in it. is that fine?
        $rowAction = $this->getMockBuilder(RowAction::class)->disableOriginalConstructor()->getMock();
        $rowAction->method('getRole')->willReturn(null);
        $rowAction->method('getColumn')->willReturn($colId);

        $this->grid->addRowAction($rowAction);

        $this->assertAttributeEquals([$colId => [$rowAction]], 'rowActions', $this->grid);
    }

    public function testAddRowActionWithGrantForActionRole()
    {
        $role = 'aRole';
        $colId = 'aColId';
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $rowAction = $this->getMockBuilder(RowAction::class)->disableOriginalConstructor()->getMock();
        $rowAction->method('getRole')->willReturn($role);
        $rowAction->method('getColumn')->willReturn($colId);
        $this->authChecker->method('isGranted')->with($role)->willReturn(true);

        $this->grid->addRowAction($rowAction);

        $this->assertAttributeEquals([$colId => [$rowAction]], 'rowActions', $this->grid);
    }

    public function testAddRowActionWithoutGrantForActionRole()
    {
        $role = 'aRole';
        // @todo: It seems that MassActionInterface does not have getRole in it. is that fine?
        $rowAction = $this->getMockBuilder(RowAction::class)->disableOriginalConstructor()->getMock();
        $rowAction->method('getRole')->willReturn($role);
        $this->authChecker->method('isGranted')->with($role)->willReturn(false);

        $this->grid->addRowAction($rowAction);

        $this->assertAttributeEmpty('rowActions', $this->grid);
    }

    public function testGetRowActions()
    {
        $colId = 'aColId';
        // @todo: It seems that RowActionInterface does not have getRole in it. is that fine?
        $rowAction = $this->getMockBuilder(RowAction::class)->disableOriginalConstructor()->getMock();
        $rowAction->method('getColumn')->willReturn($colId);

        $this->grid->addRowAction($rowAction);

        $this->assertEquals([$colId => [$rowAction]], $this->grid->getRowActions());
    }

    public function testSetExportTwigTemplateInstance()
    {
        $templateName = 'templateName';
        $template = $this->getMockBuilder(\Twig_Template::class)->disableOriginalConstructor()->getMock();
        $template->method('getTemplateName')->willReturn($templateName);

        $result = '__SELF__' . $templateName;

        $this->session->expects($this->once())->method('set')->with($this->anything(), [Grid::REQUEST_QUERY_TEMPLATE => $result]);

        $this->grid->setTemplate($template);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_TEMPLATE => $result], 'sessionData', $this->grid);
    }

    public function testSetExportStringTemplate()
    {
        $template = 'templateString';
        $this->session->expects($this->once())->method('set')->with($this->anything(), [Grid::REQUEST_QUERY_TEMPLATE => $template]);

        $this->grid->setTemplate($template);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_TEMPLATE => $template], 'sessionData', $this->grid);
    }

    public function testRaiseExceptionIfSetTemplateWithNoValidValue()
    {
        $template = true;
        $this->session->expects($this->never())->method('set')->with($this->anything(), $this->anything());

        $this->expectException(\Exception::class);
        $this->grid->setTemplate($template);

        $this->assertAttributeEquals([], 'sessionData', $this->grid);
    }

    public function testSetExportNullTemplate()
    {
        $template = null;
        $this->session->expects($this->never())->method('set')->with($this->anything(), $this->anything());

        $this->grid->setTemplate($template);

        $this->assertAttributeEquals([], 'sessionData', $this->grid);
    }

    public function testReturnTwigTemplate()
    {
        $templateName = 'templateName';
        $template = $this->getMockBuilder(\Twig_Template::class)->disableOriginalConstructor()->getMock();
        $template->method('getTemplateName')->willReturn($templateName);

        $result = '__SELF__' . $templateName;

        $this->grid->setTemplate($template);

        $this->assertEquals($result, $this->grid->getTemplate());
    }

    public function testReturnStringTemplate()
    {
        $template = 'templateString';

        $this->grid->setTemplate($template);

        $this->assertEquals($template, $this->grid->getTemplate());
    }

    public function testAddExportWithoutRole()
    {
        $export = $this->createMock(ExportInterface::class);
        $export->method('getRole')->willReturn(null);

        $this->grid->addExport($export);

        $this->assertAttributeEquals([$export], 'exports', $this->grid);
    }

    public function testAddExportWithGrantForActionRole()
    {
        $role = 'aRole';
        $export = $this->createMock(ExportInterface::class);
        $export->method('getRole')->willReturn($role);
        $this->authChecker->method('isGranted')->with($role)->willReturn(true);

        $this->grid->addExport($export);

        $this->assertAttributeEquals([$export], 'exports', $this->grid);
    }

    public function testAddExportWithoutGrantForActionRole()
    {
        $role = 'aRole';
        $export = $this->createMock(ExportInterface::class);
        $export->method('getRole')->willReturn($role);
        $this->authChecker->method('isGranted')->with($role)->willReturn(false);

        $this->grid->addExport($export);

        $this->assertAttributeEmpty('exports', $this->grid);
    }

    public function testGetExports()
    {
        $export = $this->createMock(ExportInterface::class);
        $export->method('getRole')->willReturn(null);

        $this->grid->addExport($export);

        $this->assertEquals([$export], $this->grid->getExports());
    }

    public function testSetRouteParameter()
    {
        $paramName = 'name';
        $paramValue = 'value';

        $otherParamName = 'name';
        $otherParamValue = 'value';

        $this->grid->setRouteParameter($paramName, $paramValue);
        $this->grid->setRouteParameter($otherParamName, $otherParamValue);

        $this->assertAttributeEquals(
            [$paramName => $paramValue, $otherParamName => $otherParamValue],
            'routeParameters',
            $this->grid
        );
    }

    public function testGetRouteParameters()
    {
        $paramName = 'name';
        $paramValue = 'value';

        $otherParamName = 'name';
        $otherParamValue = 'value';

        $this->grid->setRouteParameter($paramName, $paramValue);
        $this->grid->setRouteParameter($otherParamName, $otherParamValue);

        $this->assertEquals(
            [$paramName => $paramValue, $otherParamName => $otherParamValue],
            $this->grid->getRouteParameters()
        );
    }

    public function testSetRouteUrl()
    {
        $url = 'url';
        $this->grid->setRouteUrl($url);

        $this->assertAttributeEquals($url, 'routeUrl', $this->grid);
    }

    public function testGetRouteUrl()
    {
        $url = 'url';
        $this->grid->setRouteUrl($url);

        $this->assertEquals($url, $this->grid->getRouteUrl());
    }

    public function testGetRouteUrlFromRequest()
    {
        $url = 'url';
        $this->request->method('get')->with('_route')->willReturn($url);
        $this->router->method('generate')->with($url, $this->anything())->willReturn($url);

        $this->assertEquals($url, $this->grid->getRouteUrl());
    }

    public function testSetId()
    {
        $id = 'id';
        $this->grid->setId($id);

        $this->assertAttributeEquals($id, 'id', $this->grid);
    }

    public function testGetId()
    {
        $id = 'id';
        $this->grid->setId($id);

        $this->assertEquals($id, $this->grid->getId());
    }

    public function testSetPersistence()
    {
        $this->grid->setPersistence(true);

        $this->assertAttributeEquals(true, 'persistence', $this->grid);
    }

    public function testGetPersistence()
    {
        $this->grid->setPersistence(true);

        $this->assertTrue($this->grid->getPersistence());
    }

    public function testSetDataJunction()
    {
        $this->grid->setDataJunction(Column::DATA_DISJUNCTION);

        $this->assertAttributeEquals(Column::DATA_DISJUNCTION, 'dataJunction', $this->grid);
    }

    public function testGetDataJunction()
    {
        $this->grid->setDataJunction(Column::DATA_DISJUNCTION);

        $this->assertEquals(Column::DATA_DISJUNCTION, $this->grid->getDataJunction());
    }

    public function testSetInvalidLimitsRaiseException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->grid->setLimits('foo');
    }

    public function testSetIntLimit()
    {
        $limit = 10;
        $this->grid->setLimits($limit);

        $this->assertAttributeEquals([$limit => (string) $limit], 'limits', $this->grid);
    }

    public function testSetArrayLimits()
    {
        $limits = [10, 50, 100];
        $this->grid->setLimits($limits);

        $this->assertAttributeEquals(array_combine($limits, $limits), 'limits', $this->grid);
    }

    public function testSetAssociativeArrayLimits()
    {
        $limits = [10 => '10', 50 => '50', 100 => '100'];
        $this->grid->setLimits($limits);

        $this->assertAttributeEquals(array_combine($limits, $limits), 'limits', $this->grid);
    }

    public function testGetLimits()
    {
        $limits = [10, 50, 100];
        $this->grid->setLimits($limits);

        $this->assertEquals(array_combine($limits, $limits), $this->grid->getLimits());
    }

    public function testSetDefaultPage()
    {
        $page = 1;
        $this->grid->setDefaultPage($page);

        $this->assertAttributeEquals($page - 1, 'page', $this->grid);
    }

    public function testSetDefaultTweak()
    {
        $tweakId = 1;
        $this->grid->setDefaultTweak($tweakId);

        $this->assertAttributeEquals($tweakId, 'defaultTweak', $this->grid);
    }

    public function testSetPageWithInvalidValueRaiseException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $page = '-1';
        $this->grid->setPage($page);
    }

    public function testSetPageWithZeroValue()
    {
        $page = 0;
        $this->grid->setPage($page);

        $this->assertAttributeEquals($page, 'page', $this->grid);
    }

    public function testSetPage()
    {
        $page = 10;
        $this->grid->setPage($page);

        $this->assertAttributeEquals($page, 'page', $this->grid);
    }

    public function testGetPage()
    {
        $page = 10;
        $this->grid->setPage($page);

        $this->assertEquals($page, $this->grid->getPage());
    }

    public function testSetMaxResultWithNullValue()
    {
        $this->grid->setMaxResults();
        $this->assertAttributeEquals(null, 'maxResults', $this->grid);
    }

    public function testSetMaxResultWithInvalidValueRaiseException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->grid->setMaxResults(-1);
    }

    // @todo: has this case sense? Should not raise exception?
    public function testSetMaxResultWithStringValue()
    {
        $maxResult = 'foo';
        $this->grid->setMaxResults($maxResult);

        $this->assertAttributeEquals($maxResult, 'maxResults', $this->grid);
    }

    public function testSetMaxResult()
    {
        $maxResult = 1;
        $this->grid->setMaxResults($maxResult);

        $this->assertAttributeEquals($maxResult, 'maxResults', $this->grid);
    }

    public function testIsNotFilteredIfNoColumnIsFiltered()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('isFiltered')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('isFiltered')->willReturn(false);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertFalse($this->grid->isFiltered());
    }

    public function testIsFilteredIfAtLeastAColumnIsFiltered()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('isFiltered')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('isFiltered')->willReturn(true);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertTrue($this->grid->isFiltered());
    }

    public function testShowTitlesIfAtLeastOneColumnHasATitle()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('getTitle')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('getTitle')->willReturn(true);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertTrue($this->grid->isTitleSectionVisible());
    }

    public function testDontShowTitlesIfNoColumnsHasATitle()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('getTitle')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('getTitle')->willReturn(false);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertFalse($this->grid->isTitleSectionVisible());
    }

    public function testDontShowTitles()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('getTitle')->willReturn(true);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);

        $this->grid->setColumns($columns);

        $this->grid->hideTitles();
        $this->assertFalse($this->grid->isTitleSectionVisible());
    }

    public function testShowFilterSectionIfAtLeastOneColumnFilterable()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('isFilterable')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('isFilterable')->willReturn(true);
        $column2->method('getType')->willReturn('text');

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertTrue($this->grid->isFilterSectionVisible());
    }

    public function testDontShowFilterSectionIfColumnVisibleTypeIsMassAction()
    {
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isFilterable')->willReturn(true);
        $column->method('getType')->willReturn('massaction');

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);

        $this->grid->setColumns($columns);

        $this->assertFalse($this->grid->isFilterSectionVisible());
    }

    public function testDontShowFilterSectionIfColumnVisibleTypeIsActions()
    {
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isFilterable')->willReturn(true);
        $column->method('getType')->willReturn('actions');

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);

        $this->grid->setColumns($columns);

        $this->assertFalse($this->grid->isFilterSectionVisible());
    }

    public function testDontShowFilterSectionIfNoColumnFilterable()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column1->method('isFilterable')->willReturn(false);

        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2->method('isFilterable')->willReturn(false);

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);

        $this->assertFalse($this->grid->isFilterSectionVisible());
    }

    public function testDontShowFilterSection()
    {
        $this->grid->hideFilters();

        $this->assertFalse($this->grid->isFilterSectionVisible());
    }

    public function testHideFilters()
    {
        $this->grid->hideFilters();

        $this->assertAttributeEquals(false, 'showFilters', $this->grid);
    }

    public function testHideTitles()
    {
        $this->grid->hideTitles();

        $this->assertAttributeEquals(false, 'showTitles', $this->grid);
    }

    public function testAddsColumnExtension()
    {
        $extension = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $columns = $this->getMockBuilder(Columns::class)->disableOriginalConstructor()->getMock();
        $columns->expects($this->once())->method('addExtension')->with($extension);
        $this->grid->setColumns($columns);

        $this->grid->addColumnExtension($extension);
    }

    public function testSetPrefixTitle()
    {
        $prefixTitle = 'prefixTitle';
        $this->grid->setPrefixTitle($prefixTitle);

        $this->assertAttributeEquals($prefixTitle, 'prefixTitle', $this->grid);
    }

    public function testGetPrefixTitle()
    {
        $prefixTitle = 'prefixTitle';
        $this->grid->setPrefixTitle($prefixTitle);

        $this->assertEquals($prefixTitle, $this->grid->getPrefixTitle());
    }

    public function testSetNoDataMessage()
    {
        $message = 'foo';
        $this->grid->setNoDataMessage($message);

        $this->assertAttributeEquals($message, 'noDataMessage', $this->grid);
    }

    public function testGetNoDataMessage()
    {
        $message = 'foo';
        $this->grid->setNoDataMessage($message);

        $this->assertEquals($message, $this->grid->getNoDataMessage());
    }

    public function testSetNoResultMessage()
    {
        $message = 'foo';
        $this->grid->setNoResultMessage($message);

        $this->assertAttributeEquals($message, 'noResultMessage', $this->grid);
    }

    public function testGetNoResultMessage()
    {
        $message = 'foo';
        $this->grid->setNoResultMessage($message);

        $this->assertEquals($message, $this->grid->getNoResultMessage());
    }

    public function testSetHiddenColumnsWithIntegerId()
    {
        $id = 1;
        $this->grid->setHiddenColumns($id);

        $this->assertAttributeEquals([$id], 'lazyHiddenColumns', $this->grid);
    }

    public function testSetHiddenColumnWithArrayOfIds()
    {
        $ids = [1, 2, 3];
        $this->grid->setHiddenColumns($ids);

        $this->assertAttributeEquals($ids, 'lazyHiddenColumns', $this->grid);
    }

    public function testSetVisibleColumnsWithIntegerId()
    {
        $id = 1;
        $this->grid->setVisibleColumns($id);

        $this->assertAttributeEquals([$id], 'lazyVisibleColumns', $this->grid);
    }

    public function testSetVisibleColumnWithArrayOfIds()
    {
        $ids = [1, 2, 3];
        $this->grid->setVisibleColumns($ids);

        $this->assertAttributeEquals($ids, 'lazyVisibleColumns', $this->grid);
    }

    public function testShowColumnsWithIntegerId()
    {
        $id = 1;
        $this->grid->showColumns($id);

        $this->assertAttributeEquals([$id => true], 'lazyHideShowColumns', $this->grid);
    }

    public function testShowColumnsArrayOfIds()
    {
        $ids = [1, 2, 3];
        $this->grid->showColumns($ids);

        $this->assertAttributeEquals([1 => true, 2 => true, 3 => true], 'lazyHideShowColumns', $this->grid);
    }

    public function testHideColumnsWithIntegerId()
    {
        $id = 1;
        $this->grid->hideColumns($id);

        $this->assertAttributeEquals([$id => false], 'lazyHideShowColumns', $this->grid);
    }

    public function testHideColumnsArrayOfIds()
    {
        $ids = [1, 2, 3];
        $this->grid->hideColumns($ids);

        $this->assertAttributeEquals([1 => false, 2 => false, 3 => false], 'lazyHideShowColumns', $this->grid);
    }

    public function testSetActionsColumnSize()
    {
        $size = 2;
        $this->grid->setActionsColumnSize($size);

        $this->assertAttributeEquals($size, 'actionsColumnSize', $this->grid);
    }

    public function testSetActionsColumnTitle()
    {
        $title = 'aTitle';
        $this->grid->setActionsColumnTitle($title);

        $this->assertAttributeEquals($title, 'actionsColumnTitle', $this->grid);
    }

    public function testClone()
    {
        $column1 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column2 = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();

        $columns = new Columns($this->authChecker);
        $columns->addColumn($column1);
        $columns->addColumn($column2);

        $this->grid->setColumns($columns);
        $grid = clone $this->grid;

        $this->assertNotSame($columns, $grid->getColumns());
    }

    public function testRaiseExceptionDuringHandleRequestIfNoSourceSetted()
    {
        $this->expectException(\LogicException::class);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $this->grid->handleRequest($request);
    }

    public function testCreateHashWithIdDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals($this->gridHash, 'hash', $this->grid);
    }

    public function testCreateHashWithMd5DuringHandleRequest()
    {
        $this->arrange($this->createMock(GridConfigInterface::class), null);

        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $sourceHash = '4f403d7e887f7d443360504a01aaa30e';
        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $source->method('getHash')->willReturn($sourceHash);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $controller = 'aController';
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->expects($this->at(1))->method('get')->with('_controller')->willReturn($controller);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals('grid_' . md5($controller . $columns->getHash() . $sourceHash), 'hash', $this->grid);
    }

    public function testResetGridSessionWhenChangeGridDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();
        $request->headers->method('get')->with('referer')->willReturn('previousGrid');

        $session->expects($this->once())->method('remove')->with($this->gridHash);

        $this->grid->handleRequest($request);
    }

    public function testResetGridSessionWhenResetFiltersIsPressedDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->method('isXmlHttpRequest')->willReturn(true);
        $request->method('get')->with($this->gridHash)->willReturn([Grid::REQUEST_QUERY_RESET => true]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();
        $request->headers->method('get')->with('referer')->willReturn('aReferer');

        $session->expects($this->once())->method('remove')->with($this->gridHash);

        $this->grid->setPersistence(true);

        $this->grid->handleRequest($request);
    }

    public function testNotResetGridSessionWhenXmlHttpRequestDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->method('isXmlHttpRequest')->willReturn(true);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $session->expects($this->never())->method('remove')->with($this->gridHash);

        $this->grid->handleRequest($request);
    }

    public function testNotResetGridSessionWhenPersistenceSettedDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->method('isXmlHttpRequest')->willReturn(true);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $session->expects($this->never())->method('remove')->with($this->gridHash);

        $this->grid->setPersistence(true);

        $this->grid->handleRequest($request);
    }

    public function testNotResetGridSessionWhenRefererIsSameGridDuringHandleRequest()
    {
        $scheme = 'http';
        $host = 'www.foo.com/';
        $basUrl = 'baseurl';
        $pathInfo = '/info';

        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->method('isXmlHttpRequest')->willReturn(true);
        $request->method('getScheme')->willReturn($scheme);
        $request->method('getHttpHost')->willReturn($host);
        $request->method('getBaseUrl')->willReturn($basUrl);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();
        $request->headers->method('get')->with('referer')->willReturn($scheme . '//' . $host . $basUrl . $pathInfo);

        $session->expects($this->never())->method('remove')->with($this->gridHash);

        $this->grid->handleRequest($request);
    }

    public function testStartNewSessionDuringHandleRequestOnFirstGridRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals(true, 'newSession', $this->grid);
    }

    public function testStartKeepSessionDuringHandleRequestNotOnFirstGridRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->method('get')->with($this->gridHash)->willReturn('sessionData');

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals(false, 'newSession', $this->grid);
    }

    public function testRaiseExceptionIfMassActionIdNotValidDuringHandleRequest()
    {
        $this->expectException(\OutOfBoundsException::class);

        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_MASS_ACTION => 10,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);
    }

    public function testRaiseExceptionIfMassActionCallbackNotValidDuringHandleRequest()
    {
        $this->expectException(\RuntimeException::class);

        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_MASS_ACTION => 0,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getCallback')->willReturn('invalidCallback');
        $this->grid->addMassAction($massAction);

        $this->grid->handleRequest($request);
    }

    public function testResetPageAndLimitIfMassActionHandleAllDataDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_MASS_ACTION                   => 0,
            Grid::REQUEST_QUERY_MASS_ACTION_ALL_KEYS_SELECTED => true,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getCallback')->willReturn(function () { });
        $this->grid->addMassAction($massAction);

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals(0, 'page', $this->grid);
        $this->assertAttributeEquals(0, 'limit', $this->grid);
    }

    public function testMassActionResponseFromCallbackDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $callbackResponse = $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_MASS_ACTION => 0,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getCallback')->willReturn(
            function () use ($callbackResponse) { return $callbackResponse; }
        );
        $this->grid->addMassAction($massAction);

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals($callbackResponse, 'massActionResponse', $this->grid);
    }

//    public function testMassActionResponseFromControllerActionDuringHandleRequest()
//    {
//        $row = $this->createMock(Row::class);
//        $rows = new Rows();
//        $rows->addRow($row);
//
//        $source = $this->createMock(Source::class);
//        $source->method('isDataLoaded')->willReturn(true);
//        $source->method('executeFromData')->willReturn($rows);
//        $source->method('getTotalCountFromData')->willReturn(0);
//        $this->grid->setSource($source);
//
//        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
//        $column->method('isPrimary')->willReturn(true);
//        $columns = new Columns($this->authChecker);
//        $columns->addColumn($column);
//        $this->grid->setColumns($columns);
//
//        $subRequest = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
//        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
//        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
//        $request->method('get')->with($this->gridHash)->willReturn([
//            Grid::REQUEST_QUERY_MASS_ACTION => 0,
//        ]);
//        $request->method('duplicate')->willReturn($subRequest);
//        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();
//
//        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
//        $massAction->method('getCallback')->willReturn('VendorBundle:Controller:Action');
//        $massAction->method('getParameters')->willReturn(['actionParam' => 1]);
//        $this->grid->addMassAction($massAction);
//
//        $response = $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock();
//        $httpKernel = $this->getMockBuilder(HttpKernel::class)->disableOriginalConstructor()->getMock();
//        $httpKernel->method('handle')->with($subRequest, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST)->willReturn($response);
//        $this->container->method('get')->withConsecutive(
//            ['router'], ['request_stack'], ['security.authorization_checker'], ['http_kernel']
//        )->willReturnOnConsecutiveCalls($this->router, $this->requestStack, $this->authChecker, $httpKernel);
//
//        $this->grid->handleRequest($request);
//
//        $this->assertAttributeEquals($response, 'massActionResponse', $this->grid);
//    }

    public function testRaiseExceptionIfExportIdNotValidDuringHandleRequest()
    {
        $this->expectException(\OutOfBoundsException::class);

        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_EXPORT => 10,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);
    }

    public function testProcessExportsDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_EXPORT => 0,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $response = $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock();
        $export = $this->createMock(ExportInterface::class);
        $export->method('getResponse')->willReturn($response);
        $this->grid->addExport($export);

        $export->expects($this->once())->method('computeData')->with($this->grid);

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals(0, 'page', $this->grid);
        $this->assertAttributeEquals(0, 'limit', $this->grid);
        $this->assertAttributeEquals(true, 'isReadyForExport', $this->grid);
        $this->assertAttributeEquals($response, 'exportResponse', $this->grid);
    }

    public function testProcessPageDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_PAGE => 2,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 2], 'sessionData', $this->grid);
    }

    public function testProcessPageWithQueryOrderingDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $column->method('getId')->willReturn('order');
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_ORDER => 'order|foo',
            Grid::REQUEST_QUERY_PAGE  => 2,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0], 'sessionData', $this->grid);
    }

    public function testProcessPageWithQueryLimitDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_LIMIT => 50,
            Grid::REQUEST_QUERY_PAGE  => 2,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0], 'sessionData', $this->grid);
    }

    public function testProcessPageWithMassActionDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $massAction = $this->getMockBuilder(MassAction::class)->disableOriginalConstructor()->getMock();
        $massAction->method('getCallback')->willReturn(function () { });
        $this->grid->addMassAction($massAction);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_MASS_ACTION => 0,
            Grid::REQUEST_QUERY_PAGE        => 2,
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0], 'sessionData', $this->grid);
    }

    public function testProcessOrderDescDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $columnId = 'columnId';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $column->method('getId')->willReturn($columnId);
        $column->method('isSortable')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_ORDER => $columnId . '|desc',
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0, Grid::REQUEST_QUERY_ORDER => $columnId . '|desc'], 'sessionData', $this->grid);
    }

    public function testProcessOrderAscDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $columnId = 'columnId';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $column->method('getId')->willReturn($columnId);
        $column->method('isSortable')->willReturn(true);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_ORDER => $columnId . '|asc',
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0, Grid::REQUEST_QUERY_ORDER => $columnId . '|asc'], 'sessionData', $this->grid);
    }

    public function testProcessOrderColumnNotSortableDuringHandleRequest()
    {
        $row = $this->createMock(Row::class);
        $rows = new Rows();
        $rows->addRow($row);

        $source = $this->createMock(Source::class);
        $source->method('isDataLoaded')->willReturn(true);
        $source->method('executeFromData')->willReturn($rows);
        $source->method('getTotalCountFromData')->willReturn(0);
        $this->grid->setSource($source);

        $columnId = 'columnId';
        $column = $this->getMockBuilder(Column::class)->disableOriginalConstructor()->getMock();
        $column->method('isPrimary')->willReturn(true);
        $column->method('getId')->willReturn($columnId);
        $column->method('isSortable')->willReturn(false);
        $columns = new Columns($this->authChecker);
        $columns->addColumn($column);
        $this->grid->setColumns($columns);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock());
        $request->method('get')->with($this->gridHash)->willReturn([
            Grid::REQUEST_QUERY_ORDER => $columnId . '|asc',
        ]);
        $request->headers = $this->getMockBuilder(HeaderBag::class)->disableOriginalConstructor()->getMock();

        $this->grid->handleRequest($request);

        $this->assertAttributeEquals([Grid::REQUEST_QUERY_PAGE => 0], 'sessionData', $this->grid);
    }

    public function testProcessTweaksDuringHandleRequest()
    {
        // @todo
    }

    public function testProcessPageWithFiltersDuringHandleRequest()
    {
        // @todo
    }

    public function testHandleRequest()
    {
        // @todo: split in more than one test if needed
    }

    public function testIsReadyForRedirect()
    {
        // @todo: split in more than one test if needed
    }

    public function testGetHashWhenCreated()
    {
        // @todo split in more than one test if needed
    }

    public function testGetTweaks()
    {
        // @todo split in more than one test if needed
    }

    public function testGetActiveTweaks()
    {
        // @todo split in more than one test if needed
    }

    public function testGetTweak()
    {
        // @todo split in more than one test if needed
    }

    public function testGetTweaksGroup()
    {
        // @todo split in more than one test if needed
    }

    public function testGetActiveTweakGroup()
    {
        // @todo split in more than one test if needed
    }

    public function testGetExportResponse()
    {
        // @todo split in more than one test if needed
    }

    public function testGetMassActionResponse()
    {
        // @todo split in more than one test if needed
    }

    public function testIsReadyForExport()
    {
        // @todo split in more than one test if needed
    }

    public function testIsMassActionRedirect()
    {
        // @todo split in more than one test if needed
    }

    public function testSetPermanentFilters()
    {
        // @todo split in more than one test if needed
    }

    public function testSetDefaultFilters()
    {
        // @todo split in more than one test if needed
    }

    public function testSetDefaultOrder()
    {
        // @todo split in more than one test if needed
    }

    public function testGetLimit()
    {
        // @todo split in more than one test if needed
    }

    public function testGetRows()
    {
        // @todo split in more than one test if needed
    }

    public function testGetPageCount()
    {
        // @todo split in more than one test if needed
    }

    public function testGetTotalCount()
    {
        // @todo split in more than one test if needed
    }

    public function testIsPagerSectionVisible()
    {
        // @todo split in more than one test if needed
    }

    public function testDeleteAction()
    {
        // @todo split in more than one test if needed
    }

    public function testGetGridResponse()
    {
        // @todo split in more than one test if needed
    }

    public function testGetRawData()
    {
        // @todo split in more than one test if needed
    }

    public function testGetFilters()
    {
        // @todo split in more than one test if needed
    }

    public function testGetFilter()
    {
        // @todo split in more than one test if needed
    }

    public function testHasFilter()
    {
        // @todo split in more than one test if needed
    }

    public function setUp()
    {
        $this->arrange($this->createMock(GridConfigInterface::class));
    }

    /**
     * @param $gridConfigInterface
     * @param string $id
     */
    private function arrange($gridConfigInterface = null, $id = 'id')
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $this->session = $session;

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getSession')->willReturn($session);
        $request->attributes = new ParameterBag([]);
        $this->request = $request;

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        $this->requestStack = $requestStack;
        $this->router = $this->getMockBuilder(Router::class)->disableOriginalConstructor()->getMock();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker = $authChecker;

        $container = $this->getMockBuilder(Container::class)->disableOriginalConstructor()->getMock();
        $container->method('get')->withConsecutive(
            ['router'], ['request_stack'], ['security.authorization_checker']
        )->willReturnOnConsecutiveCalls($this->router, $requestStack, $authChecker);
        $this->container = $container;

        $this->gridId = $id;
        $this->gridHash = 'grid_' . $this->gridId;

        $this->grid = new Grid($container, $this->gridId, $gridConfigInterface);
    }
}
