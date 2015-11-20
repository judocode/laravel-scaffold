(function(app, moduleName){
    try {
        app = angular.module(moduleName);
    } catch(err) {
        app = angular.module(moduleName, []);
    }

    app.controller('SettingsController',
        ['$scope', '$http', '$filter' , '$rootScope',
            function($scope, $http, $filter, $rootScope) {
                var _this = this;
                var orderBy = $filter('orderBy');
                $scope.SettingsCtrl = this;

                //member data
                angular.extend(this, {
                    isLoaded        : false,
                    isEmpty         : false,
                    isReverse       : true,
                    itemsPerPage    : 10,
                    countItems      : 0,
                    field           : 'name',
                    settings        : [],
                    search          : [],
                    columns     : [
                        {'field': 'id','title': 'ID'},
                        {'field': 'type','title': 'Type'},
                        {'field': 'name','title': 'Name'},
                        {'field': 'value','title': 'Value'},
                        {'field': 'extraparams','title': 'Extraparams'}
                    ]
                });

                //methods
                angular.extend(this, {
                    loadList: function(){
                        _this.isLoaded = false;
                        $http.get('/v2/api/all-settings').success(function(d){
                            _this.settings = d.data;
                            _this.countItems = d.count;
                            _this.order(_this.field);
                            _this.isLoaded = true;
                            _this.isEmpty = (d.count != 0) ? false: true;
                        });
                    },
                    order : function(d){
                        _this.field = d;
                        _this.isReverse = !_this.isReverse;
                        _this.settings = orderBy(_this.settings, d, _this.isReverse);
                    }
                });

                _this.loadList();

                //event listener

                var createListener = $rootScope.$on('settingsCtrl.create', function (event, data) {
                    _this.settings = data.data;
                });

                var updateListener = $rootScope.$on('settingsCtrl.update', function (event, data) {
                    //$scope.advertisers = data.data;
                });

                $scope.$on('$destroy', function(){
                    createListener();
                    updateListener();
                });
            }]);
}(app, "app"));

