var app = angular.module('app',['angularUtils.directives.dirPagination','ui.bootstrap']);
app.config(function($interpolateProvider, $httpProvider) {
    $interpolateProvider.startSymbol('[[');
    $interpolateProvider.endSymbol(']]');

    $httpProvider.defaults.headers.common["X-Requested-With"] = 'XMLHttpRequest';
});
app.filter('dateToISO', function() {
    return function(input) {
        try{
            return new Date(input).toISOString();
        }catch (err){
            return '';
        }
    };
});
app.factory('Scopes', ['$rootScope', function ($rootScope) {
    var mem = {};

    return {
        store: function (key, value) {
            mem[key] = value;
        },
        get: function (key) {
            return mem[key];
        }
    };
}]);
app.directive('showModal', ['$modal', '$http' , '$log', '$q', '$rootScope', function($modal, $http, $log, $q, $rootScope){
    return {
        restrict : 'C',
        scope: {
            myItem : '=item',
            myResultEvent : '@expect',
            myAction : '@action'
        },
        link: function (scope, element, attrs) {
            element.bind('click', function (e) {
                e.preventDefault();
                scope.openModal(this.href);
                scope.$apply();

            });

            scope.$on('$destroy', function(){
                element.unbind('click');
            });

            scope.openModal = function (url) {
                //var deferred = $q.defer();
                var originalObj = {};
                var modalInstance = $modal.open({
                    animation: true,
                    templateUrl: url,
                    controller: 'ModalInstanceController',
                    resolve: {
                        items: function () {
                            if(scope.myItem != undefined) {
                                originalObj = angular.extend({}, scope.myItem);
                            }
                            return {
                                bind    :   scope.myItem,
                                url     :   url,
                                expect	: 	scope.myResultEvent,
                                action	:	scope.myAction
                            };
                        }
                    }
                });

                modalInstance.result.then(function (data) {
                    console.log('data');
                    console.log(data);

                }, function () {
                    if(scope.myItem != undefined) {
                        angular.extend(scope.myItem, originalObj);
                    }
                    console.log('cancelled');
                });
            };
        }
    };
}]);
