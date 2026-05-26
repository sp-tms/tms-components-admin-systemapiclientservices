<?php

namespace Apps\Tms\Components\System\Api\Client\Services;

use Apps\Core\Packages\Adminltetags\Traits\DynamicTable;
use System\Base\BaseComponent;
use System\Base\Exceptions\ControllerNotFoundException;

class ServicesComponent extends BaseComponent
{
    use DynamicTable;

    protected $apiPackage;

    public function initialize()
    {
        $this->apiPackage = $this->usePackage('apiClientServices');
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        $this->view->apiCategories = $this->apiPackage->apiCategories;

        $this->view->apiLocations = $this->apiPackage->apiLocations;

        if (isset($this->getData()['id'])) {
            if (isset($this->getData()['clone'])) {
                $this->view->clone = true;
            }

            if ($this->getData()['id'] != 0) {
                $api = $this->apiPackage->getApiById($this->getData()['id']);

                if (isset($this->getData()['repository'])) {
                    $api['repository'] = true;
                }

                if (isset($api['used_by']) && $api['used_by'] !== '') {
                    if (is_string($api['used_by'])) {
                        $api['used_by'] = $this->helper->decode($api['used_by'], true);
                    }
                    $api['used_by'] = implode(', ', $api['used_by']);
                } else if (isset($api['used_by']) && $api['used_by'] === '') {
                    $api['used_by'] = '-';
                }
            } else {
                $api = [];
                $api['setup'] = 0;

                if (isset($this->getData()['repository'])) {
                    $api['category'] = 'repos';
                    $api['location'] = 'system';
                    $api['repository'] = true;
                }

                if (isset($this->getData()['category']) && isset($this->getData()['provider'])) {
                    $api['category'] = $this->getData()['category'];
                    $api['provider'] = $this->getData()['provider'];

                    //Check if provider class exists
                    if (!$this->apiPackage->useApi([
                            'config' =>
                                [
                                    'category'     => $this->getData()['category'],
                                    'provider'     => $this->getData()['provider'],
                                    'checkOnly'    => true//Set this to check if the API exists and can be instantiated.
                                ]
                        ])
                    ) {
                        throw new ControllerNotFoundException;
                    }

                    $api['location'] = $this->apiPackage->apiLocation;
                }
            }

            $this->view->api = $api;

            $this->view->pick('services/view');

            return;
        }

        if ($this->request->isPost()) {
            $replaceColumns =
                function ($dataArr) {
                    if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                        return $this->replaceColumns($dataArr);
                    }

                    return $dataArr;
                };
        } else {
            $replaceColumns = null;
        }

        $controlActions =
            [
                'actionsToEnable'       =>
                [
                    'edit'      => 'system/api/client/services',
                    'clone'     => 'system/api/client/services',
                    'remove'    => 'system/api/client/services/remove'
                ]
            ];

        $conditions =
            [
                'conditions'    => '-|app_type|equals|' . $this->apps->getAppInfo()['app_type'] . '&'
            ];

        $this->generateDTContent(
            $this->apiPackage,
            'system/api/client/services/view',
            $conditions,
            ['name', 'category', 'provider', 'in_use', 'used_by', 'setup'],
            true,
            ['name', 'category', 'provider', 'in_use', 'used_by', 'setup'],
            $controlActions,
            null,
            $replaceColumns,
            'name'
        );

        $this->view->pick('services/list');
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            if (isset($data['in_use']) && $data['in_use'] == '1') {
                $data['in_use'] = '<span class="badge badge-success text-uppercase">Yes</span>';
            } else {
                $data['in_use'] = '<span class="badge badge-secondary text-uppercase">No</span>';
            }

            $data['category'] = ucfirst($data['category']);

            if (isset($data['used_by']) && $data['used_by'] !== '') {
                if (is_string($data['used_by'])) {
                    $data['used_by'] = $this->helper->decode($data['used_by'], true);
                }
                $data['used_by'] = implode(', ', $data['used_by']);
            } else if (isset($data['used_by']) && $data['used_by'] === '') {
                $data['used_by'] = '-';
            }

            if (isset($data['setup']) && $data['setup'] == '1') {
                $data['setup'] ='<a href="' . $this->links->url('system/api/q/') . '" type="button" data-id="" data-rowid="" class="pl-2 pr-2 text-white btn btn-primary btn-xs rowSetup contentAjaxLink"><i class="mr-1 fas fa-fw fa-xs fa-magic"></i> <span class="text-xs text-uppercase">Complete Setup</span></a>';
            } else if (isset($data['setup']) && $data['setup'] == '2') {
                $data['setup'] = '<a href="' . $this->links->url('system/api/q/') . '" type="button" data-id="" data-rowid="" class="pl-2 pr-2 text-white btn btn-primary btn-xs rowSetup contentAjaxLink"><i class="mr-1 fas fa-fw fa-xs fa-magic"></i> <span class="text-xs text-uppercase">Complete Setup</span></a>';
            } else if (isset($data['setup']) && $data['setup'] == '3') {
                $data['setup'] = '<a href="' . $this->links->url('system/api/q/') . '" type="button" data-id="" data-rowid="" class="pl-2 pr-2 text-white btn btn-primary btn-xs rowSetup contentAjaxLink"><i class="mr-1 fas fa-fw fa-xs fa-magic"></i> <span class="text-xs text-uppercase">Complete Setup</span></a>';
            } else if (isset($data['setup']) && $data['setup'] == '4') {
                $data['setup'] = '<h6><span class="badge badge-success">Complete</span></h6>';
            } else if (isset($data['setup']) && $data['setup'] == '5') {
                $data['setup'] = '<a href="' . $this->links->url('system/api/q/') . '" type="button" data-id="" data-rowid="" class="pl-2 pr-2 text-white btn btn-primary btn-xs rowSetup contentAjaxLink"><i class="mr-1 fas fa-fw fa-xs fa-magic"></i> <span class="text-xs text-uppercase">Refresh Token</span></a>';//When token is about to expire <= 10 days
            }
        }

        return $dataArr;
    }

    /**
     * @acl(name=add)
     */
    public function addAction()
    {
        $this->requestIsPost();

        $responseData = [];

        if ($this->apiPackage->addApi($this->postData())) {
            $responseData = $this->apiPackage->packagesData->last;
        }

        $this->addResponse(
            $this->apiPackage->packagesData->responseMessage,
            $this->apiPackage->packagesData->responseCode,
            $responseData
        );
    }

    /**
     * @acl(name=update)
     */
    public function updateAction()
    {
        $this->requestIsPost();

        $this->apiPackage->updateApi($this->postData());

        $this->addResponse(
            $this->apiPackage->packagesData->responseMessage,
            $this->apiPackage->packagesData->responseCode
        );
    }

    /**
     * @acl(name=remove)
     */
    public function removeAction()
    {
        $this->requestIsPost();

        $this->apiPackage->removeApi($this->postData());

        $this->addResponse(
            $this->apiPackage->packagesData->responseMessage,
            $this->apiPackage->packagesData->responseCode
        );
    }
}
