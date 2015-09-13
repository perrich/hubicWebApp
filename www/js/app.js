var app = angular.module("filestorage", [
  "ngRoute",
  "app.config",
  "ui.bootstrap.contextMenu",
  "filestorage.services",
  "filestorage.controllers",
  "ngAnimate", 
  "toaster"
]);

app.config(['$compileProvider', function ($compileProvider) {
  $compileProvider.debugInfoEnabled(true);
}]);

app.config(["$routeProvider", function ($routeProvider) {
  $routeProvider
  .when("/key", {
    templateUrl: "partials/form.key.html",
    controller: "KeyController as form"
  })
  .when("/folder", {
    templateUrl: "partials/folder.html",
    controller: "FolderController as folder"
  })
  .when("/folder/:folder", {
      templateUrl: "partials/folder.html",
      controller: "FolderController as folder"
  })
  .when("/about", {
    templateUrl: "partials/about.html"
  })
    .otherwise({ redirectTo: "/key" });
}]);