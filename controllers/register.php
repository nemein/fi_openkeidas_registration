<?php
class fi_openkeidas_registration_controllers_register
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_form()
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
            return;
        }

        $user = new fi_openkeidas_registration_user();
        $this->data['form'] = $this->generate_form($user);

        midgardmvc_core::get_instance()->head->set_title('Rekisteröityminen');
    }

    public function post_form()
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
            return;
        }

        midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_registration'); 

        // Read values from POST
        $user = new fi_openkeidas_registration_user();
        $form = $this->generate_form($user);
        $form->process_post(); 

        // Populate user
        midgardmvc_helper_forms_mgdschema::form_to_object($form, $user);

        $this->create_account($user);
    }

    private function generate_form(fi_openkeidas_registration_user $user)
    {
        $form = midgardmvc_helper_forms_mgdschema::create($user, false);

        // Set labels
        $form->firstname->widget->set_label('Etunimi');
        $form->lastname->widget->set_label('Sukunimi');
        $form->memberid->widget->set_label('OAJ:n jäsennumero');
        $form->email->widget->set_label('Sähköposti');
        $form->school->widget->set_label('Koulu');
        $form->municipality->widget->set_label('Kunta');

        // Set all fields to required
        foreach ($form->items as $item)
        {
            $item->set_required(true);
        }

        return $form;
    }

    private function create_account(fi_openkeidas_registration_user $user)
    {
    }
}
