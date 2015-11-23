// copy the following to "mv app/views/[models]/script.blade.php public/angular/controllers/[model].js"
(function(app, moduleName){
    try {
        app = angular.module(moduleName);
    } catch(err) {
        app = angular.module(moduleName, []);
    }

    app.controller('[Models]Controller',
        ['$scope', '$http', '$filter' , '$rootScope',
            function($scope, $http, $filter, $rootScope) {
                var _this = this;
                var orderBy = $filter('orderBy');
                $scope.[Models]Ctrl = this;

                //member data
                angular.extend(this, {
                    isLoaded        : false,
                    isEmpty         : false,
                    isReverse       : true,
                    itemsPerPage    : 10,
                    countItems      : 0,
                    field           : 'name',
                    [models]        : [],
                    search          : [],
                    columns     : [
                        [repeat]{'field': '[property]','title': '[Property]'},
						[/repeat]
                    ]
                });

                //methods
                angular.extend(this, {
                    loadList: function(){
                        _this.isLoaded = false;
                        $http.get('/api/all-[models]').success(function(d){
                            _this.[models] = d.data;
                            _this.countItems = d.count;
                            _this.order(_this.field);
                            _this.isLoaded = true;
                            _this.isEmpty = (d.count != 0) ? false: true;
                        });
                    },
                    order : function(d){
                        _this.field = d;
                        _this.isReverse = !_this.isReverse;
                        _this.[models] = orderBy(_this.[models], d, _this.isReverse);
                    }
                });

                _this.loadList();

                //event listener

                var createListener = $rootScope.$on('[models]Ctrl.create', function (event, data) {
                    _this.[models] = data.data;
                });

                var updateListener = $rootScope.$on('[models]Ctrl.update', function (event, data) {
                    //$scope.[models] = data.data;
                });

                $scope.$on('$destroy', function(){
                    createListener();
                    updateListener();
                });
            }]);
}(app, "myApp"));

