(function(app, moduleName){
    try {
        app = angular.module(moduleName);
    } catch(err) {
        app = angular.module(moduleName, []);
    }

    app.controller('ModalInstanceController',
        ['$scope', '$http', '$filter' ,'$modal', '$modalInstance','$rootScope', 'items',
            function($scope, $http, $filter, $modal, $modalInstance, $rootScope, items) {
                var url,
                    str,
                    newUrl,
                    actionUrl;
                $scope.hasError = false;

                $scope.bind = (items.bind != undefined) ? items.bind : {};
                var originalObj = angular.extend({}, items.bind);

                if (items.action == 'same') {
                    actionUrl = items.url;
                } else {
                    url = items.url;
                    str = url.substr(url.lastIndexOf('/'));
                    newUrl = url.replace(new RegExp(str), '');

                    actionUrl = (items.action != undefined) ? items.action : newUrl;
                }

                var expectEvent = items.expect;



                $scope.ok = function () {
                    $scope.hasError = false;

                    if($scope.bind.id != undefined){
                        if(items.action == 'delete')
                        {
                            actionUrl = newUrl;
                            $http.delete(actionUrl)
                                .success(function (d) {
                                    if (expectEvent != undefined && expectEvent != '') {
                                        $rootScope.$emit(expectEvent, d);
                                    }
                                    $modalInstance.close(d);
                                })
                                .error(function (d) {
                                    console.log(d);
                                    console.log('error occurred');
                                    $scope.errors = d;
                                    $scope.hasError = true;
                                });
                        }else {
                            $http.put(actionUrl, $scope.bind)
                                .success(function (d) {
                                    if (expectEvent != undefined && expectEvent != '') {
                                        $rootScope.$emit(expectEvent, d);
                                    }
                                    $modalInstance.close(d);
                                })
                                .error(function (d) {
                                    console.log(d);
                                    console.log('error occurred');
                                    $scope.errors = d;
                                    $scope.hasError = true;
                                });
                        }
                    }else{
                        $http.post(actionUrl, $scope.bind)
                            .success(function(d){
                                if(expectEvent != undefined && expectEvent != '')
                                {
                                    $rootScope.$emit(expectEvent, d);
                                }
                                $modalInstance.close(d);
                            })
                            .error(function(d){
                                //cause form to error and focus on specific field
                                $scope.errors = d;
                                $scope.hasError = true;
                            });
                    }
                };

                $scope.cancel = function () {
                    angular.extend($scope.bind, originalObj);
                    $modalInstance.dismiss('cancel');
                };
            }]);
}(app, "app"));
