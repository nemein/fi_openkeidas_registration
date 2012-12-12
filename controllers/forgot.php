<?php
class fi_openkeidas_registration_controllers_forgot
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

        $this->data['form'] = $this->generate_form();
        midgardmvc_core::get_instance()->head->set_title('Unohtunut salasana');
    }

    public function post_form()
    {
        if (midgardmvc_core::get_instance()->authentication->is_user())
        {
            midgardmvc_core::get_instance()->head->relocate('/');
            return;
        }

        $form = $this->generate_form();
        $form->process_post();

        $password = $this->generate_password();
        $this->update_account($form->email->value, $password);
        $this->send_password($form->email->value, $password);
    }

    private function generate_form()
    {
        $form = midgardmvc_helper_forms::create('fi_openkeidas_registration_forgot');
        $field = $form->add_field('email', 'email', true);
        $widget = $field->set_widget('email');
        $widget->set_label('Sähköpostiosoite');
        return $form;
    }

    private function generate_password()
    {
        return substr(hash('sha512', rand()), 0, 6);
    }

    private function update_account($email, $password)
    {
        $tokens = array(
            'login' => $email,
            'authtype' => 'SHA1',
            'active' => true
        );
        try
        {
            $user = new midgard_user($tokens);
            if ($user)
            {
                $user->password = sha1($password);
                midgardmvc_core::get_instance()->authorization->enter_sudo('fi_openkeidas_registration'); 
                $user->update();
                midgardmvc_core::get_instance()->authorization->leave_sudo();
            }
        }
        catch (midgard_error_exception $e)
        {
            midgardmvc_core::get_instance()->uimessages->add(array(
                'title' => 'Tunnusta ei löytynyt',
                'message' => 'Antamallasi osoitteella ei löytynyt tunnusta.',
                'type' => 'ok'
            ));
            midgardmvc_core::get_instance()->head->relocate('/rekisterointi/unohtunut');
        }
    }

    private function send_password($email, $password)
    {
        $mail = new ezcMailComposer();
        $mail->from = new ezcMailAddress('noreply@openkeidas.fi', 'Open Keidas');
        $mail->addTo(new ezcMailAddress($email, ''));
        $mail->subject = 'Open Keidas -tunnuksesi';
        $mail->plainText = "Hei,\n\nUusi Open Keidas-salasanasi on: {$password}\n\nVoit käyttää sitä kirjautuaksesi osoitteessa http://openkeidas.fi/mgd:login";
        $mail->build();
        $transport = new ezcMailMtaTransport();
        $transport->send($mail);
    }
}
